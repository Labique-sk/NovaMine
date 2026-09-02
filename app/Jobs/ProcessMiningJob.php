<?php

namespace App\Jobs;

use App\Models\MiningJob;
use App\Services\Mining\MiningPipeline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMiningJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public MiningJob $miningJob)
    {
    }

    public function handle(MiningPipeline $pipeline): void
    {
        $pipeline->run($this->miningJob);
    }
}
