<?php

namespace App\Jobs;

use App\Models\SubjectTopicExtraction;
use App\Services\SubjectTopicExtractionService;
use App\SubjectTopicExtractionError;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExtractSubjectTopics implements ShouldQueue
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
        $this->tries = min(5, max(1, (int) config('ai.subject_topic_extraction.job_tries', 2)));
        $this->backoff = array_map(
            fn (int $seconds): int => min(600, max(1, $seconds)),
            config('ai.subject_topic_extraction.job_backoff', [15, 45]),
        );
        $this->timeout = max(1, (int) config('ai.subject_topic_extraction.timeout', 240)) + 60;
    }

    public function handle(SubjectTopicExtractionService $service): void
    {
        $service->extract($this->extractionId);
    }

    public function failed(?Throwable $exception): void
    {
        SubjectTopicExtraction::query()
            ->whereKey($this->extractionId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'error_code' => SubjectTopicExtractionError::ExtractionFailed->value,
                'updated_at' => now(),
            ]);
    }
}
