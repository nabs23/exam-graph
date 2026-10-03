<?php

use App\CurriculumExtractionStatus;
use App\FileEmbeddingStatus;
use App\Jobs\ExtractOfficialCurriculum;
use App\Models\CurriculumExtraction;
use App\Models\FilePageEmbedding;
use App\Models\ProgramFile;
use App\Services\OfficialCurriculumExtractionService;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\RateLimitedException;

function pendingCurriculumExtractionForPolling(): CurriculumExtraction
{
    $file = ProgramFile::factory()->create([
        'content_hash' => str_repeat('a', 64),
        'embedding_status' => FileEmbeddingStatus::Complete,
    ]);
    $extraction = CurriculumExtraction::factory()->for($file)->for($file->program)->create([
        'status' => CurriculumExtractionStatus::Processing,
        'proposal' => null,
        'source_files' => [['id' => $file->id, 'content_hash' => $file->content_hash, 'title' => $file->title]],
    ]);
    $extraction->forceFill(['provider_response_id' => 'resp_test', 'provider_started_at' => now()])->save();

    return $extraction;
}

test('failed extraction jobs retain an actionable provider failure', function (Throwable $cause, string $code, string $messageFragment): void {
    $extraction = CurriculumExtraction::factory()->create(['status' => CurriculumExtractionStatus::Processing]);
    $exception = new RuntimeException('Provider failed', previous: $cause);

    (new ExtractOfficialCurriculum($extraction->id))->failed($exception);

    expect($extraction->fresh()->error_code)->toBe($code)
        ->and($extraction->fresh()->error_message)->toContain($messageFragment);
})->with([
    'HTTP timeout' => [new ConnectionException('cURL error 28: Operation timed out'), 'provider_connection_timeout', 'connection'],
    'connection failure' => [new ConnectionException('cURL error 7: Failed to connect'), 'provider_connection_failed', 'connect'],
    'invalid credentials' => [new RequestException(new Response(new PsrResponse(401, [], '{"error":{"code":"invalid_api_key"}}'))), 'provider_authentication_failed', 'credentials'],
    'missing model' => [new RequestException(new Response(new PsrResponse(404, [], '{"error":{"code":"model_not_found"}}'))), 'provider_model_unavailable', 'model'],
    'rate limit' => [new RequestException(new Response(new PsrResponse(429))), 'provider_rate_limited', 'rate limit'],
    'quota exhausted' => [new RequestException(new Response(new PsrResponse(429, [], '{"error":{"code":"insufficient_quota"}}'))), 'provider_insufficient_credits', 'credits'],
    'provider unavailable' => [new RequestException(new Response(new PsrResponse(503))), 'provider_unavailable', 'temporarily unavailable'],
    'worker timeout' => [new TimeoutExceededException('Worker timed out'), 'job_timeout', 'worker'],
    'SDK wrapped quota failure' => [RateLimitedException::forProvider('openai', 0, new RequestException(new Response(new PsrResponse(429, [], '{"error":{"code":"insufficient_quota"}}')))), 'provider_insufficient_credits', 'credits'],
]);

test('one extraction wait setting determines the job execution limit', function (int $wait, int $jobLimit): void {
    config(['ai.official_curriculum.timeout' => $wait]);

    expect((new ExtractOfficialCurriculum(1))->timeout)->toBe($jobLimit);
})->with([[0, 60], [240, 300], [600, 660]]);

test('unlimited background extraction keeps polling without resubmitting the model request', function (): void {
    $this->freezeTime();
    config(['ai.official_curriculum.timeout' => 0]);
    $extraction = pendingCurriculumExtractionForPolling();
    $extraction->forceFill(['provider_started_at' => now()->subDay()])->save();
    Bus::fake([ExtractOfficialCurriculum::class]);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses/resp_test' => Http::response(['status' => 'in_progress'])]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Processing);
    Bus::assertDispatched(ExtractOfficialCurriculum::class, fn (ExtractOfficialCurriculum $job): bool => $job->extractionId === $extraction->id);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
    Http::assertSentCount(1);
});

test('a finite extraction wait cancels an overdue background response', function (): void {
    $this->freezeTime();
    config(['ai.official_curriculum.timeout' => 240]);
    $extraction = pendingCurriculumExtractionForPolling();
    $extraction->forceFill(['provider_started_at' => now()->subSeconds(241)])->save();
    Bus::fake([ExtractOfficialCurriculum::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses/resp_test' => Http::response(['status' => 'in_progress']),
        'https://api.openai.com/v1/responses/resp_test/cancel' => Http::response(['status' => 'cancelled']),
    ]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Failed)
        ->and($extraction->fresh()->error_code)->toBe('provider_timeout');
    Bus::assertNotDispatched(ExtractOfficialCurriculum::class);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/cancel'));
});

test('a completed background response allows uncited suggestions for review', function (array $citation, ?int $expectedPage, bool $invalidParent = false): void {
    config(['ai.official_curriculum.timeout' => 0]);
    $extraction = pendingCurriculumExtractionForPolling();
    $file = $extraction->programFile;
    FilePageEmbedding::query()->create([
        'program_file_id' => $file->id, 'source_key' => 'program_file:'.$file->id,
        'page_number' => 1, 'source_hash' => $file->content_hash, 'provider' => 'voyageai',
        'model' => 'voyage-4', 'dimensions' => 1024, 'embedding' => array_fill(0, 1024, 0.0),
        'content' => 'Official subject: Biology.', 'embedded_at' => now(),
    ]);
    $proposal = ['subjects' => [[
        'name' => 'Biology', 'code' => null, 'description' => 'The study of living systems.',
        'description_origin' => 'ai_generated', ...$citation,
        'topics' => [[
            'title' => 'Suggested topic beyond the references', 'description' => null,
            'description_origin' => 'unavailable', ...$citation,
            'parent_index' => $invalidParent ? 0 : null,
        ]],
    ]]];
    if (($citation['source_file_id'] ?? null) === 1) {
        $proposal['subjects'][0]['source_file_id'] = $file->id;
        $proposal['subjects'][0]['topics'][0]['source_file_id'] = $file->id;
    }
    Bus::fake([ExtractOfficialCurriculum::class]);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses/resp_test' => Http::response([
        'status' => 'completed',
        'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($proposal)]]]],
    ])]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe($invalidParent ? CurriculumExtractionStatus::Failed : CurriculumExtractionStatus::Reviewing);
    if (! $invalidParent) {
        expect($extraction->fresh()->proposal['subjects'][0]['source_page'])->toBe($expectedPage)
            ->and($extraction->fresh()->proposal['subjects'][0]['source_file_id'])->toBe($expectedPage === null ? null : $file->id)
            ->and($extraction->fresh()->proposal['subjects'][0]['topics'][0]['source_page'])->toBe($expectedPage)
            ->and($extraction->fresh()->proposal['subjects'][0]['topics'][0]['title'])->toBe('Suggested topic beyond the references');
        $this->assertDatabaseCount('subjects', 0);
        $this->assertDatabaseCount('syllabus_topics', 0);
    } else {
        expect($extraction->fresh()->error_code)->toBe('invalid_proposal')
            ->and($extraction->fresh()->proposal)->toBeNull();
    }
    Bus::assertNotDispatched(ExtractOfficialCurriculum::class);
    Http::assertSentCount(1);
})->with([
    'valid citation' => [['source_file_id' => 1, 'source_page' => 1], 1],
    'page absent from source' => [['source_file_id' => 1, 'source_page' => 2], null],
    'unknown file' => [['source_file_id' => 999999, 'source_page' => 1], null],
    'null citation' => [['source_file_id' => null, 'source_page' => null], null],
    'omitted citation' => [[], null],
    'invalid hierarchy still fails' => [[], null, true],
]);

test('terminal background failures explain why extraction ended', function (array $response, string $expectedCode, string $messageFragment): void {
    config(['ai.official_curriculum.timeout' => 0]);
    $extraction = pendingCurriculumExtractionForPolling();
    Bus::fake([ExtractOfficialCurriculum::class]);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses/resp_test' => Http::response($response)]);

    app(OfficialCurriculumExtractionService::class)->extract($extraction->id);

    expect($extraction->fresh()->status)->toBe(CurriculumExtractionStatus::Failed)
        ->and($extraction->fresh()->error_code)->toBe($expectedCode)
        ->and($extraction->fresh()->error_message)->toContain($messageFragment);
    Bus::assertNotDispatched(ExtractOfficialCurriculum::class);
    Http::assertSentCount(1);
})->with([
    'model unavailable' => [['status' => 'failed', 'error' => ['code' => 'model_not_found']], 'provider_model_unavailable', 'model'],
    'rate limit' => [['status' => 'failed', 'error' => ['code' => 'rate_limit_exceeded']], 'provider_rate_limited', 'rate limit'],
    'output limit' => [['status' => 'incomplete', 'incomplete_details' => ['reason' => 'max_output_tokens']], 'provider_output_limit', 'output limit'],
    'cancelled' => [['status' => 'cancelled'], 'provider_cancelled', 'cancelled'],
    'refused' => [['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal']]]]], 'provider_refused', 'declined'],
]);
