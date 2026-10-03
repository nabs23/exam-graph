<?php

use App\Ai\Agents\OfficialCurriculumExtractor;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('curriculum requests apply configured reasoning effort only to compatible OpenAI models', function (string $model, ?string $expectedEffort, string $configuredEffort = 'low'): void {
    config([
        'ai.providers.openai.key' => 'test-key',
        'ai.providers.openai.url' => 'https://api.openai.com/v1',
        'ai.official_curriculum.reasoning_effort' => $configuredEffort,
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/responses' => Http::response([
            'id' => 'resp_curriculum_test',
            'model' => $model,
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'role' => 'assistant',
                'content' => [['type' => 'output_text', 'text' => '{"subjects":[]}']],
            ]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]),
    ]);

    $response = (new OfficialCurriculumExtractor(extractionModel: $model))->prompt(
        '{"source_pages":[]}',
        provider: 'openai',
        model: $model,
    );

    expect($response->toArray())->toBe(['subjects' => []]);
    Http::assertSent(function (Request $request) use ($model, $expectedEffort): bool {
        return $request->url() === 'https://api.openai.com/v1/responses'
            && $request['model'] === $model
            && ($expectedEffort === null
                ? ! isset($request['reasoning'])
                : $request['reasoning'] === ['effort' => $expectedEffort]);
    });
    Http::assertSentCount(1);
})->with([
    'Sol 6.1' => ['gpt-6.1-sol', 'low'],
    'Sol' => ['gpt-6-sol', 'low'],
    'Astra' => ['gpt-6-astra', 'low'],
    'Luna' => ['gpt-6-luna', 'low'],
    'non-reasoning model' => ['gpt-4.1', null],
    'custom reasoning effort' => ['gpt-6.1-sol', 'medium', 'medium'],
]);

test('background agents preserve the response identifier while OpenAI is still processing', function (): void {
    config(['ai.providers.openai.key' => 'test-key', 'ai.providers.openai.url' => 'https://api.openai.com/v1']);
    Http::preventStrayRequests();
    Http::fake(['https://api.openai.com/v1/responses' => Http::response([
        'id' => 'resp_background_test', 'model' => 'gpt-6.1-sol', 'status' => 'queued', 'output' => [],
    ])]);

    $response = (new OfficialCurriculumExtractor(extractionModel: 'gpt-6.1-sol', background: true))->prompt(
        '{"source_pages":[]}', provider: 'openai', model: 'gpt-6.1-sol', timeout: 30,
    );

    expect($response->raw?->json('id'))->toBe('resp_background_test')
        ->and($response->raw?->json('status'))->toBe('queued');
    Http::assertSent(fn (Request $request): bool => $request['background'] === true);
});
