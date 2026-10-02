<?php

namespace App\Jobs;

use App\Models\CurriculumExtraction;
use App\Services\OfficialCurriculumExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractOfficialCurriculum implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var array<int, int> */
    public array $backoff;

    public int $tries;

    public int $timeout;

    public function __construct(public int $extractionId)
    {
        $this->tries = min(5, max(1, (int) config('ai.official_curriculum.job_tries', 2)));
        $this->backoff = array_map(
            fn (int $seconds): int => min(600, max(1, $seconds)),
            config('ai.official_curriculum.job_backoff', [15, 45]),
        );
        $this->timeout = min(300, max(1, (int) config('ai.official_curriculum.job_timeout', 120)));
    }

    public function handle(OfficialCurriculumExtractionService $service): void
    {
        $service->extract($this->extractionId, $this->attempts() > 1);
    }

    public function failed(?Throwable $exception): void
    {
        CurriculumExtraction::query()
            ->whereKey($this->extractionId)
            ->whereIn('status', ['queued', 'processing'])
            ->update(['status' => 'failed', 'error_code' => 'provider_failed', 'updated_at' => now()]);
    }
}
