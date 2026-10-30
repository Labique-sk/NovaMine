<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCandidateRequest;
use App\Http\Requests\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\Shortlist;
use App\Services\Intelligence\CandidateScoringService;
use App\Services\Mining\CandidateDeduplicator;
use App\Services\Mining\CandidateValidator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CandidateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Candidate::query()->ownedBy($user)->with('skills');

        if ($q = $request->string('q')->toString()) {
            $query->where(function (Builder $inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")
                    ->orWhere('designation', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%")
                    ->orWhere('professional_email', 'like', "%{$q}%")
                    ->orWhereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$q}%"));
            });
        }

        foreach (['location', 'industry', 'source_name', 'verification_status', 'designation'] as $column) {
            if ($request->filled($column)) {
                $value = $request->string($column);
                // Use LIKE for location to handle partial matches
                if ($column === 'location') {
                    $query->where($column, 'like', '%' . $value . '%');
                } else {
                    $query->where($column, $value);
                }
            }
        }
        if ($request->filled('skill')) {
            $skill = $request->string('skill');
            $query->whereHas('skills', fn (Builder $s) => $s->where('name', 'like', "%{$skill}%"));
        }
        if ($request->filled('role')) {
            $query->where('designation', 'like', '%'.$request->string('role').'%');
        }
        if ($request->filled('min_score')) {
            $query->where(function (Builder $query) use ($request) {
                $query->where('match_score', '>=', $request->integer('min_score'))
                      ->orWhereNull('match_score');
            });
        }
        if ($request->filled('experience_min')) {
            $query->where('years_experience', '>=', $request->integer('experience_min'));
        }
        if ($request->filled('experience_max')) {
            $query->where('years_experience', '<=', $request->integer('experience_max'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        $sort = $request->string('sort', 'match_score')->toString();
        $dir = $request->string('dir', 'desc')->toString() === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'name' => 'name',
            'role' => 'designation',
            'company' => 'company',
            'location' => 'location',
            'experience' => 'years_experience',
            'score' => 'match_score',
            'created_at' => 'created_at',
        ];
        $column = $sortMap[$sort] ?? 'match_score';
        $query->orderBy($column, $dir)->orderByDesc('id');

        $page = $query->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => collect($page->items())->map->toSearchArray()->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(CreateCandidateRequest $request, CandidateValidator $validator, CandidateDeduplicator $deduplicator, CandidateScoringService $scoring): JsonResponse
    {
        $user = $request->user();
        
        // Prepare candidate data in the format expected by existing services
        $record = array_merge($request->validated(), [
            'is_synthetic' => false,
            'source_name' => $request->input('source_name', 'Manual Entry'),
            'source_url' => $request->input('source_url'),
        ]);

        // Normalize the record using existing normalizer
        $normalizer = app(\App\Services\Mining\CandidateNormalizer::class);
        $normalized = $normalizer->normalize($record);

        // Validate using existing validator
        $validation = $validator->validate($normalized);
        $normalized['verification_status'] = $validation['status'];
        $normalized['email_validation_status'] = $validation['email'];
        $normalized['phone_validation_status'] = $validation['phone'];
        $normalized['profile_validation_status'] = $validation['profile'];
        $normalized['validation'] = $validation;

        // Check for duplicates using existing deduplicator
        $dedupeResult = $deduplicator->dedupe($user, [$normalized]);
        
        if (empty($dedupeResult['unique'])) {
            return response()->json([
                'message' => 'This candidate already exists in your database.',
                'duplicates' => $dedupeResult['duplicates'],
            ], 409);
        }

        // Score the candidate using existing scoring service
        $recordToSave = $dedupeResult['unique'][0];
        $criteria = ['role' => $recordToSave['designation'] ?? ''];
        $score = $scoring->score($recordToSave, $criteria);
        $recordToSave['score'] = $score;
        $recordToSave['match_score'] = $score['match_score'] ?? null;
        $recordToSave['score_breakdown'] = $score;

        // Save using existing database logic from mining pipeline
        $candidate = DB::transaction(function () use ($user, $recordToSave) {
            $candidate = Candidate::query()->create([
                'user_id' => $user->id,
                'mining_job_id' => null, // Manual entry has no mining job
                'name' => $recordToSave['name'],
                'designation' => $recordToSave['designation'] ?? null,
                'company' => $recordToSave['company'] ?? null,
                'years_experience' => $recordToSave['years_experience'] ?? null,
                'industry' => $recordToSave['industry'] ?? null,
                'location' => $recordToSave['location'] ?? null,
                'professional_email' => $recordToSave['professional_email'] ?? null,
                'phone' => $recordToSave['phone'] ?? null,
                'profile_url' => $recordToSave['profile_url'] ?? null,
                'portfolio_url' => $recordToSave['portfolio_url'] ?? null,
                'source_url' => $recordToSave['source_url'] ?? null,
                'source_name' => $recordToSave['source_name'] ?? 'Manual Entry',
                'source_snippet' => $recordToSave['source_snippet'] ?? null,
                'candidate_type' => $recordToSave['candidate_type'] ?? null,
                'verification_status' => $recordToSave['verification_status'] ?? 'unverified',
                'email_validation_status' => $recordToSave['email_validation_status'] ?? null,
                'phone_validation_status' => $recordToSave['phone_validation_status'] ?? null,
                'profile_validation_status' => $recordToSave['profile_validation_status'] ?? null,
                'duplicate_status' => $recordToSave['duplicate_status'] ?? 'unique',
                'match_score' => $recordToSave['match_score'] ?? null,
                'score_breakdown' => $recordToSave['score_breakdown'] ?? null,
                'is_synthetic' => (bool) ($recordToSave['is_synthetic'] ?? false),
            ]);

            // Save skills
            foreach ($recordToSave['skills'] ?? [] as $skill) {
                $matching = in_array($skill, $recordToSave['score']['matching_skills'] ?? [], true);
                $candidate->skills()->create(['name' => $skill, 'is_match' => $matching]);
            }

            // Save education
            foreach ($recordToSave['education'] ?? [] as $edu) {
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

            // Save contacts
            if (! empty($recordToSave['professional_email'])) {
                $candidate->contacts()->create(['type' => 'email', 'value' => $recordToSave['professional_email'], 'is_public' => true]);
            }
            if (! empty($recordToSave['phone'])) {
                $candidate->contacts()->create(['type' => 'phone', 'value' => $recordToSave['phone'], 'is_public' => true]);
            }

            // Save source
            $candidate->sources()->create([
                'source_name' => $recordToSave['source_name'] ?? 'Manual Entry',
                'source_url' => $recordToSave['source_url'] ?? null,
                'collected_at' => now(),
            ]);

            // Save validation results
            foreach ($recordToSave['validation']['results'] ?? [] as $result) {
                $candidate->validationResults()->create([
                    'type' => $result['type'],
                    'status' => $result['status'],
                    'message' => $result['message'] ?? null,
                    'http_status' => $result['http_status'] ?? null,
                    'checked_at' => now(),
                ]);
            }

            return $candidate;
        });

        return response()->json(['candidate' => $this->detail($candidate->fresh(['skills', 'education', 'sources', 'validationResults', 'shortlists']))], 201);
    }

    public function show(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('view', $candidate);
        $candidate->load(['skills', 'education', 'sources', 'validationResults', 'shortlists']);

        return response()->json(['candidate' => $this->detail($candidate)]);
    }

    public function update(UpdateCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $candidate->fill($request->validated())->save();

        return response()->json(['candidate' => $this->detail($candidate->fresh(['skills', 'education', 'sources', 'validationResults', 'shortlists']))]);
    }

    public function destroy(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('delete', $candidate);
        $candidate->delete();

        return response()->json(['ok' => true]);
    }

    public function addToShortlist(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $request->validate(['shortlist_id' => ['nullable', 'integer']]);
        $user = $request->user();

        $shortlist = $request->filled('shortlist_id')
            ? Shortlist::query()->where('user_id', $user->id)->findOrFail($request->integer('shortlist_id'))
            : Shortlist::query()->firstOrCreate(['user_id' => $user->id, 'name' => 'Saved candidates']);

        $this->authorize('update', $shortlist);
        $shortlist->candidates()->syncWithoutDetaching([$candidate->id]);

        return response()->json(['ok' => true, 'shortlist_id' => $shortlist->id]);
    }

    public function removeFromShortlist(Request $request, Candidate $candidate): JsonResponse
    {
        $this->authorize('update', $candidate);
        $user = $request->user();
        $query = Shortlist::query()->where('user_id', $user->id);
        if ($request->filled('shortlist_id')) {
            $query->where('id', $request->integer('shortlist_id'));
        }
        $query->get()->each(fn (Shortlist $list) => $list->candidates()->detach($candidate->id));

        return response()->json(['ok' => true]);
    }

    private function detail(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'designation' => $candidate->designation,
            'company' => $candidate->company,
            'location' => $candidate->location,
            'years_experience' => $candidate->years_experience,
            'industry' => $candidate->industry,
            'professional_email' => $candidate->professional_email,
            'phone' => $candidate->phone,
            'profile_url' => $candidate->profile_url,
            'portfolio_url' => $candidate->portfolio_url,
            'source_url' => $candidate->source_url,
            'source_name' => $candidate->source_name,
            'source_snippet' => $candidate->source_snippet,
            'verification_status' => $candidate->verification_status,
            'email_validation_status' => $candidate->email_validation_status,
            'phone_validation_status' => $candidate->phone_validation_status,
            'profile_validation_status' => $candidate->profile_validation_status,
            'duplicate_status' => $candidate->duplicate_status,
            'match_score' => $candidate->match_score,
            'score_breakdown' => $candidate->score_breakdown,
            'is_synthetic' => $candidate->is_synthetic,
            'created_at' => $candidate->created_at,
            'skills' => $candidate->skills->pluck('name'),
            'education' => $candidate->education,
            'sources' => $candidate->sources,
            'validation' => $candidate->validationResults,
            'shortlist_ids' => $candidate->shortlists->pluck('id'),
        ];
    }
}
