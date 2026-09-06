<?php

namespace App\Services\Analytics;

use App\Models\Candidate;
use App\Models\ExportRecord;
use App\Models\MiningJob;
use App\Models\Search;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    public function dashboard(User $user): array
    {
        $candidates = Candidate::query()->ownedBy($user);
        $total = (clone $candidates)->count();
        $verified = (clone $candidates)->where('verification_status', 'verified')->count();
        $jobs = MiningJob::query()->where('user_id', $user->id)->count();
        $avg = round((float) (clone $candidates)->avg('match_score'), 1);

        $previousMonth = (clone $candidates)->where('created_at', '<', now()->startOfMonth())->count();
        $thisMonth = (clone $candidates)->where('created_at', '>=', now()->startOfMonth())->count();

        return [
            'totals' => [
                'candidates' => $total,
                'verified' => $verified,
                'jobs' => $jobs,
                'average_score' => $avg,
                'candidate_trend' => $this->trend($thisMonth, $previousMonth),
            ],
            'acquisition' => $this->dailyCounts($user, 14),
            'sources' => $this->groupCounts($user, 'source_name'),
            'skills' => $this->topSkills($user),
            'recent_candidates' => Candidate::query()->ownedBy($user)->with('skills')->latest()->limit(8)->get()->map->toSearchArray(),
            'recent_searches' => Search::query()->where('user_id', $user->id)->latest()->limit(5)->get(),
            'recent_exports' => ExportRecord::query()->where('user_id', $user->id)->latest()->limit(5)->get(),
            'activity' => $user->activityLogs()->latest()->limit(8)->get(),
        ];
    }

    public function analytics(User $user): array
    {
        $total = Candidate::query()->ownedBy($user)->count();
        $verified = Candidate::query()->ownedBy($user)->where('verification_status', 'verified')->count();
        $duplicates = Candidate::query()->ownedBy($user)->where('duplicate_status', 'merged')->count();
        $jobs = MiningJob::query()->where('user_id', $user->id);
        $completed = (clone $jobs)->where('status', 'completed')->count();
        $failed = (clone $jobs)->where('status', 'failed')->count();
        $exports = ExportRecord::query()->where('user_id', $user->id)->count();
        $avgDuration = (int) Search::query()->where('user_id', $user->id)->avg('duration_ms');

        return [
            'validation_rate' => $total ? round(($verified / $total) * 100, 1) : 0,
            'duplicate_rate' => $total ? round(($duplicates / $total) * 100, 1) : 0,
            'average_processing_ms' => $avgDuration,
            'exports' => $exports,
            'mining_success_rate' => ($completed + $failed) ? round(($completed / ($completed + $failed)) * 100, 1) : 0,
            'average_match_score' => round((float) Candidate::query()->ownedBy($user)->avg('match_score'), 1),
            'validation_quality' => $this->weeklyVerification($user),
            'locations' => $this->groupCounts($user, 'location'),
            'industries' => $this->groupCounts($user, 'industry'),
            'experience' => $this->experienceBuckets($user),
            'volume' => $this->weekdayVolume($user),
            'skills' => $this->topSkills($user),
            'sources' => $this->groupCounts($user, 'source_name'),
            'acquisition' => $this->dailyCounts($user, 14),
        ];
    }

    private function trend(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? '▲ new this month' : 'No prior period';
        }
        $pct = round((($current - $previous) / $previous) * 100, 1);

        return ($pct >= 0 ? '▲ ' : '▼ ').abs($pct).'% vs prior';
    }

    /**
     * @return array{labels:list<string>,data:list<int>}
     */
    private function dailyCounts(User $user, int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $rows = Candidate::query()
            ->ownedBy($user)
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $labels = [];
        $data = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i)->toDateString();
            $labels[] = (string) ($i + 1);
            $data[] = (int) ($rows[$day] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array<string, int>
     */
    private function groupCounts(User $user, string $column): array
    {
        return Candidate::query()
            ->ownedBy($user)
            ->whereNotNull($column)
            ->select($column, DB::raw('COUNT(*) as c'))
            ->groupBy($column)
            ->orderByDesc('c')
            ->limit(8)
            ->pluck('c', $column)
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function topSkills(User $user): array
    {
        return DB::table('candidate_skills')
            ->join('candidates', 'candidates.id', '=', 'candidate_skills.candidate_id')
            ->where('candidates.user_id', $user->id)
            ->select('candidate_skills.name', DB::raw('COUNT(*) as c'))
            ->groupBy('candidate_skills.name')
            ->orderByDesc('c')
            ->limit(8)
            ->pluck('c', 'name')
            ->all();
    }

    /**
     * @return array{labels:list<string>,data:list<float>}
     */
    private function weeklyVerification(User $user): array
    {
        $labels = [];
        $data = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = now()->subWeeks($i)->startOfWeek();
            $end = (clone $start)->endOfWeek();
            $total = Candidate::query()->ownedBy($user)->whereBetween('created_at', [$start, $end])->count();
            $verified = Candidate::query()->ownedBy($user)->whereBetween('created_at', [$start, $end])->where('verification_status', 'verified')->count();
            $labels[] = 'W'.(6 - $i);
            $data[] = $total ? round(($verified / $total) * 100, 1) : 0;
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array<string, int>
     */
    private function experienceBuckets(User $user): array
    {
        $rows = Candidate::query()->ownedBy($user)->whereNotNull('years_experience')->pluck('years_experience');
        $buckets = ['0-2' => 0, '3-5' => 0, '6-8' => 0, '9+' => 0];
        foreach ($rows as $years) {
            if ($years <= 2) {
                $buckets['0-2']++;
            } elseif ($years <= 5) {
                $buckets['3-5']++;
            } elseif ($years <= 8) {
                $buckets['6-8']++;
            } else {
                $buckets['9+']++;
            }
        }

        return $buckets;
    }

    /**
     * @return array{labels:list<string>,data:list<int>}
     */
    private function weekdayVolume(User $user): array
    {
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $data = array_fill(0, 7, 0);

        $driver = config('database.default');

        // Build driver-specific SQL to extract day-of-week as 0=Sunday..6=Saturday
        if ($driver === 'sqlite') {
            $rows = Candidate::query()
                ->ownedBy($user)
                ->selectRaw('CAST(strftime("%w", created_at) AS INTEGER) as dow, COUNT(*) as c')
                ->groupBy('dow')
                ->pluck('c', 'dow');

            // SQLite: dow 0 = Sunday
            foreach ($rows as $dow => $count) {
                $idx = ((int) $dow + 6) % 7; // map Sunday(0) -> 6, Monday(1) -> 0, ...
                $data[$idx] = (int) $count;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        if ($driver === 'mysql' || $driver === 'mysql2') {
            // MySQL: DAYOFWEEK returns 1=Sunday..7=Saturday
            $rows = Candidate::query()
                ->ownedBy($user)
                ->selectRaw('DAYOFWEEK(created_at) as dow, COUNT(*) as c')
                ->groupBy('dow')
                ->pluck('c', 'dow');

            foreach ($rows as $dow => $count) {
                $idx = ((int) $dow + 5) % 7; // map Sunday(1) -> 6, Monday(2) -> 0, ...
                $data[$idx] = (int) $count;
            }

            return ['labels' => $labels, 'data' => $data];
        }

        // Fallback / PostgreSQL: use EXTRACT(DOW FROM timestamp) -> 0=Sunday..6=Saturday
        $rows = Candidate::query()
            ->ownedBy($user)
            ->selectRaw('CAST(EXTRACT(DOW FROM created_at) AS INTEGER) as dow, COUNT(*) as c')
            ->groupBy('dow')
            ->pluck('c', 'dow');

        foreach ($rows as $dow => $count) {
            $idx = ((int) $dow + 6) % 7; // map Sunday(0) -> 6, Monday(1) -> 0, ...
            $data[$idx] = (int) $count;
        }

        return ['labels' => $labels, 'data' => $data];
    }
}
