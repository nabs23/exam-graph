<?php

namespace App\Jobs;

use App\CurriculumGenerationStatus;
use App\Models\CurriculumGeneration;
use App\Services\CurriculumGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateCurriculumProposal implements ShouldBeUnique, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public int $uniqueFor = 3600;

    public function __construct(public int $generationId)
    {
        $this->tries = min(5, max(1, (int) config('ai.curriculum_generation.job_tries', 2)));
        $this->timeout = max(1, (int) config('ai.curriculum_generation.timeout', 240)) + 60;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return array_map(
            fn (int $seconds): int => min(600, max(1, $seconds)),
            config('ai.curriculum_generation.job_backoff', [15, 45]),
        );
    }

    public function uniqueId(): string
    {
        return (string) $this->generationId;
    }

    public function handle(CurriculumGenerationService $service): void
    {
        $service->generate($this->generationId);
    }

    public function failed(?Throwable $exception): void
    {
        CurriculumGeneration::query()
            ->whereKey($this->generationId)
            ->whereIn('status', [CurriculumGenerationStatus::Queued, CurriculumGenerationStatus::Generating])
            ->update([
                'status' => CurriculumGenerationStatus::Failed,
                'error_code' => 'provider_failed',
                'updated_at' => now(),
            ]);
    }
}
