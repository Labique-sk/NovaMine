<?php

namespace App\Services\Mining;

use App\Models\ActivityLog;
use App\Models\Candidate;
use App\Models\MiningJob;
use App\Models\Search;
use App\Services\Intelligence\CandidateScoringService;
use App\Services\Sources\SourceAdapterRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class MiningPipeline
{
    /** @var list<string> */
    public const STAGES = [
        'Initializing',
        'Searching sources',
        'Collecting candidates',
        'Normalizing records',
        'Validating profiles',
        'Checking emails',
        'Checking phone numbers',
        'Removing duplicates',
        'Calculating match scores',
        'Saving candidates',
        'Completed',
    ];

    public function __construct(
        private readonly SourceAdapterRegistry $adapters,
        private readonly CandidateNormalizer $normalizer,
        private readonly CandidateValidator $validator,
        private readonly CandidateDeduplicator $deduplicator,
        private readonly CandidateScoringService $scoring,
    ) {
    }

    public function run(MiningJob $job): void
    {
        $job->refresh();
        if ($job->status === 'cancelled') {
            return;
        }

        $criteria = array_merge(['role' => $job->query], $job->filters ?? []);
        $started = microtime(true);

        try {
            $this->stage($job, 'running', self::STAGES[0], 5);
            $this->stage($job, 'running', self::STAGES[1], 12);

            $collected = [];
            foreach ($this->adapters->enabled() as $adapter) {
                $job->refresh();
                if ($job->status === 'cancelled') {
                    return;
                }
                $collected = array_merge($collected, $adapter->collect($criteria));
            }

            $this->stage($job, 'running', self::STAGES[2], 24, ['total_found' => count($collected)]);
            $this->stage($job, 'running', self::STAGES[3], 36);

            $normalized = [];
            foreach ($collected as $record) {
                $item = $this->normalizer->normalize($record);
                if ($item['name'] !== '') {
                    $normalized[] = $item;
                }
            }

            $this->stage($job, 'validating', self::STAGES[4], 48);
            $this->stage($job, 'validating', self::STAGES[5], 58);
            $this->stage($job, 'validating', self::STAGES[6], 66);

            $validated = [];
            foreach ($normalized as $record) {
                $record['validation'] = $this->validator->validate($record);
                $record['verification_status'] = $record['validation']['status'];
                $record['email_validation_status'] = $record['validation']['email'];
                $record['phone_validation_status'] = $record['validation']['phone'];
                $record['profile_validation_status'] = $record['validation']['profile'];
                $validated[] = $record;
            }

            $this->stage($job, 'validating', self::STAGES[7], 74);
            $deduped = $this->deduplicator->dedupe($job->user, $validated);

            $this->stage($job, 'running', self::STAGES[8], 84);
            $toSave = [];
            foreach ($deduped['unique'] as $record) {
                if ($record instanceof Candidate) {
                    continue;
                }
                $record['score'] = $this->scoring->score($record, $criteria);
                $toSave[] = $record;
            }

            $this->stage($job, 'running', self::STAGES[9], 92);
            $saved = 0;
            $verified = 0;

            DB::transaction(function () use ($job, $toSave, &$saved, &$verified) {
                foreach ($toSave as $record) {
                    $candidate = Candidate::query()->create([
                        'user_id' => $job->user_id,
                        'mining_job_id' => $job->id,
                        'name' => $record['name'],
                        'designation' => $record['designation'] ?? null,
                        'company' => $record['company'] ?? null,
                        'years_experience' => $record['years_experience'] ?? null,
                        'industry' => $record['industry'] ?? null,
                        'location' => $record['location'] ?? null,
                        'professional_email' => $record['professional_email'] ?? null,
                        'phone' => $record['phone'] ?? null,
                        'profile_url' => $record['profile_url'] ?? null,
                        'portfolio_url' => $record['portfolio_url'] ?? null,
                        'source_url' => $record['source_url'] ?? null,
                        'source_name' => $record['source_name'] ?? null,
                        'candidate_type' => $record['candidate_type'] ?? null,
                        'verification_status' => $record['verification_status'] ?? 'unverified',
                        'email_validation_status' => $record['email_validation_status'] ?? null,
                        'phone_validation_status' => $record['phone_validation_status'] ?? null,
                        'profile_validation_status' => $record['profile_validation_status'] ?? null,
                        'duplicate_status' => $record['duplicate_status'] ?? 'unique',
                        'match_score' => $record['score']['match_score'] ?? null,
                        'score_breakdown' => $record['score'] ?? null,
                        'is_synthetic' => (bool) ($record['is_synthetic'] ?? false),
                    ]);

                    foreach ($record['skills'] ?? [] as $skill) {
                        $matching = in_array($skill, $record['score']['matching_skills'] ?? [], true);
                        $candidate->skills()->create(['name' => $skill, 'is_match' => $matching]);
                    }
                    foreach ($record['education'] ?? [] as $edu) {
                        if (is_string($edu)) {
                            $edu = ['credential' => $edu];
                        }
                        $candidate->education()->create([
                            'institution' => $edu['institution'] ?? null,
                            'credential' => $edu['credential'] ?? null,
                            'field' => $edu['field'] ?? null,
                            'year' => $edu['year'] ?? null,
                        ]);
                    }
                    if (! empty($record['professional_email'])) {
                        $candidate->contacts()->create(['type' => 'email', 'value' => $record['professional_email'], 'is_public' => true]);
                    }
                    if (! empty($record['phone'])) {
                        $candidate->contacts()->create(['type' => 'phone', 'value' => $record['phone'], 'is_public' => true]);
                    }
                    $candidate->sources()->create([
                        'source_name' => $record['source_name'] ?? 'unknown',
                        'source_url' => $record['source_url'] ?? null,
                        'collected_at' => now(),
                    ]);
                    foreach ($record['validation']['results'] ?? [] as $result) {
                        $candidate->validationResults()->create([
                            'type' => $result['type'],
                            'status' => $result['status'],
                            'message' => $result['message'] ?? null,
                            'http_status' => $result['http_status'] ?? null,
                            'checked_at' => now(),
                        ]);
                    }

                    $saved++;
                    if (($record['verification_status'] ?? null) === 'verified') {
                        $verified++;
                    }
                }
            });

            $job->update([
                'status' => 'completed',
                'stage' => self::STAGES[10],
                'progress' => 100,
                'processed' => $saved,
                'verified' => $verified,
                'duplicates' => $deduped['duplicates'],
                'completed_at' => now(),
            ]);

            Search::query()->create([
                'user_id' => $job->user_id,
                'mining_job_id' => $job->id,
                'query' => $job->query,
                'filters' => $job->filters,
                'result_count' => $saved,
                'status' => 'completed',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            ActivityLog::query()->create([
                'user_id' => $job->user_id,
                'action' => 'mining.completed',
                'subject_type' => MiningJob::class,
                'subject_id' => $job->id,
                'properties' => ['processed' => $saved, 'duplicates' => $deduped['duplicates']],
            ]);
        } catch (Throwable $e) {
            Log::error('Mining job failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            $job->update([
                'status' => 'failed',
                'error_message' => 'Mining source temporarily unavailable.',
                'completed_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function stage(MiningJob $job, string $status, string $stage, int $progress, array $extra = []): void
    {
        $job->refresh();
        if ($job->status === 'cancelled') {
            return;
        }

        $job->fill(array_merge([
            'status' => $status,
            'stage' => $stage,
            'progress' => $progress,
            'started_at' => $job->started_at ?? now(),
        ], $extra))->save();
    }
}
