<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = Search::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($searches);
    }

    public function destroy(Request $request, Search $search): JsonResponse
    {
        abort_unless($search->user_id === $request->user()->id, 404);

        $job = $search->miningJob;
        if ($job !== null && $job->user_id !== $request->user()->id) {
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
