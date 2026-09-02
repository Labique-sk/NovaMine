<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StartMiningRequest;
use App\Jobs\ProcessMiningJob;
use App\Models\ActivityLog;
use App\Models\MiningJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MiningController extends Controller
{
    public function store(StartMiningRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->safe()->except(['role']);

        $job = MiningJob::query()->create([
            'user_id' => $user->id,
            'query' => $request->string('role'),
            'filters' => $filters,
            'status' => 'queued',
            'stage' => 'Queued',
            'progress' => 0,
        ]);

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'action' => 'mining.started',
            'subject_type' => MiningJob::class,
            'subject_id' => $job->id,
            'properties' => ['query' => $job->query],
        ]);

        ProcessMiningJob::dispatch($job);

        return response()->json(['job' => $job], 201);
    }

    public function show(Request $request, MiningJob $mining): JsonResponse
    {
        $this->authorize('view', $mining);

        return response()->json([
            'job' => $mining,
            'stages' => \App\Services\Mining\MiningPipeline::STAGES,
        ]);
    }

    public function cancel(Request $request, MiningJob $mining): JsonResponse
    {
        $this->authorize('update', $mining);

        if (! $mining->isCancellable()) {
            return response()->json(['message' => 'This mining job can no longer be cancelled.'], 422);
        }

        $mining->update([
            'status' => 'cancelled',
            'stage' => 'Cancelled',
            'completed_at' => now(),
        ]);

        return response()->json(['job' => $mining->fresh()]);
    }
}
