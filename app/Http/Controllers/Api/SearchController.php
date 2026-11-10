<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMiningJob;
use App\Models\Candidate;
use App\Models\MiningJob;
use App\Models\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $baseQuery = Search::query()->where('user_id', $request->user()->id);
        $summary = (clone $baseQuery)
            ->selectRaw(
                'COUNT(*) AS saved_searches, COALESCE(SUM(result_count), 0) AS candidate_records, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed_searches',
                ['completed'],
            )
            ->first();
        $searches = (clone $baseQuery)
            ->latest()
            ->paginate(min(100, max(1, $request->integer('per_page', 100))));

        return response()->json(array_merge($searches->toArray(), [
            'summary' => [
                'saved_searches' => (int) ($summary?->saved_searches ?? 0),
                'candidate_records' => (int) ($summary?->candidate_records ?? 0),
                'completed_searches' => (int) ($summary?->completed_searches ?? 0),
            ],
        ]));
    }

    public function remine(Request $request, Search $search): JsonResponse
    {
        abort_unless((int) $search->user_id === (int) $request->user()->id, 404);

        $job = $search->miningJob;
        if (! $job instanceof MiningJob || (int) $job->user_id !== (int) $request->user()->id) {
            return response()->json(['message' => 'This saved search has no mining run to refresh.'], 409);
        }
        if ($job->isCancellable()) {
            return response()->json(['message' => 'This search is already running.'], 409);
        }

        $filters = $search->filters ?? $job->filters ?? [];
        $filters['max_iterations'] = 100;
        $job->forceFill([
            'query' => $search->query,
            'filters' => $filters,
            'status' => 'queued',
            'stage' => 'Queued',
            'progress' => 0,
            'total_found' => 0,
            'processed' => 0,
            'verified' => 0,
            'duplicates' => 0,
            'started_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ])->save();

        try {
            ProcessMiningJob::dispatchSync($job);
        } catch (\Throwable) {
            $search->forceFill(['status' => 'failed'])->save();

            return response()->json([
                'message' => $job->fresh()->error_message ?: 'Re-mining failed. Please try again later.',
                'job' => $job->fresh(),
            ], 500);
        }

        return response()->json([
            'message' => 'Saved search refreshed.',
            'search' => $search->fresh(),
            'job' => $job->fresh(),
        ]);
    }

    public function destroy(Request $request, Search $search): JsonResponse
    {
        abort_unless((int) $search->user_id === (int) $request->user()->id, 404);

        $job = $search->miningJob;
        if ($job !== null && (int) $job->user_id !== (int) $request->user()->id) {
            abort(404);
        }
        if ($job !== null && $job->isCancellable()) {
            return response()->json(['message' => 'This search is still running and cannot be deleted yet.'], 409);
        }

        $deletedCandidates = 0;
        $dataRetainedForAnotherSearch = false;

        DB::transaction(function () use ($request, $search, $job, &$deletedCandidates, &$dataRetainedForAnotherSearch): void {
            if ($job !== null) {
                $hasOtherSearches = Search::query()
                    ->where('user_id', $request->user()->id)
                    ->where('mining_job_id', $job->id)
                    ->where('id', '!=', $search->id)
                    ->exists();

                if ($hasOtherSearches) {
                    $dataRetainedForAnotherSearch = true;
                } else {
                    $deletedCandidates = Candidate::query()
                        ->where('user_id', $request->user()->id)
                        ->where('mining_job_id', $job->id)
                        ->delete();
                    $job->delete();
                }
            }

            $search->delete();
        });

        $message = $dataRetainedForAnotherSearch
            ? 'Search history deleted. Candidate data was kept because another saved search uses this run.'
            : "Search and {$deletedCandidates} candidate record(s) deleted.";

        return response()->json([
            'message' => $message,
            'deleted_candidates' => $deletedCandidates,
        ]);
    }
}
