<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class OfficialCurriculumExtractor implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public ?string $extractionModel = null, public bool $background = false) {}

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        if (! in_array($provider, [Lab::OpenAI, 'openai'], true)) {
            return [];
        }

        $options = $this->background ? ['background' => true] : [];
        if (str_starts_with($this->extractionModel ?? '', 'gpt-6')) {
            $options['reasoning'] = ['effort' => config('ai.official_curriculum.reasoning_effort', 'low')];
        }

        return $options;
    }

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You propose program subjects and syllabus topics for human review, using the supplied program-file page text as reference material.
Preserve official names and codes when available. You may suggest relevant subjects and topics that are not explicitly present in the references; reviewers decide what to approve. Use null for codes that are not supplied by the sources.
Provide a concise, useful description for every subject and topic. Preserve a description from the source when one is available, otherwise draft a relevant description. Set description_origin to "source" when the description is stated or directly supported by the source, and "ai_generated" when you have drafted it. Use a null description and "unavailable" when no useful description can be provided.
Do not return instructional concepts, learning objectives, prerequisites, exams, or study advice.
When a supplied page supports a subject or topic, cite its source_file_id and page_number as source_page. Otherwise set both source_file_id and source_page to null. Never invent a citation.
Each topic's parent_index is null for a top-level topic or a zero-based index of another topic in that same subject's topics array. The parent must appear before its child.
Return an empty subjects array only when the references provide insufficient context to propose a relevant curriculum.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'subjects' => $schema->array()->max(100)->items(
                $schema->object(fn (JsonSchema $subject): array => [
                    'code' => $subject->string()->max(50)->nullable()->required(),
                    'name' => $subject->string()->max(255)->required(),
                    'description' => $subject->string()->max(2000)->nullable()->required(),
                    'description_origin' => $subject->string()->enum(['source', 'ai_generated', 'unavailable'])->required(),
                    'source_file_id' => $subject->integer()->min(1)->nullable()->required(),
                    'source_page' => $subject->integer()->min(1)->nullable()->required(),
                    'topics' => $subject->array()->max(500)->items(
                        $subject->object(fn (JsonSchema $topic): array => [
                            'code' => $topic->string()->max(50)->nullable()->required(),
                            'title' => $topic->string()->max(255)->required(),
                            'description' => $topic->string()->max(2000)->nullable()->required(),
                            'description_origin' => $topic->string()->enum(['source', 'ai_generated', 'unavailable'])->required(),
                            'source_file_id' => $topic->integer()->min(1)->nullable()->required(),
                            'source_page' => $topic->integer()->min(1)->nullable()->required(),
                            'parent_index' => $topic->integer()->min(0)->nullable()->required(),
                        ]),
                    )->required(),
                ]),
            )->required(),
        ];
    }
}
