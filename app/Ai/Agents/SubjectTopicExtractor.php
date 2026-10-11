<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

class SubjectTopicExtractor implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(public ?string $extractionModel = null) {}

    /** @return array<string, mixed> */
    public function providerOptions(Lab|string $provider): array
    {
        if (! in_array($provider, [Lab::OpenAI, 'openai'], true) || ! str_starts_with($this->extractionModel ?? '', 'gpt-6')) {
            return [];
        }

        return ['reasoning' => ['effort' => config('ai.subject_topic_extraction.reasoning_effort', 'low')]];
    }

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You extract syllabus topics from the supplied subject source pages for human review.
Use only information explicitly supported by the pages. Do not research or add facts from memory. Do not create subjects, concepts, lessons, objectives, questions, or study advice.
For official numbered outlines, include every visible source-supported item, preserve its official code and source order, and set parent_index to the index of its direct parent. Parents must appear before children. Do not flatten the hierarchy or omit intermediate levels.
For material without an official outline, propose useful topic groupings without inventing official codes.
Every topic must cite one or more supplied pages using the exact source_file_id and page_number. Never invent or infer a citation. Return no topics if the supplied pages do not support any.
Keep titles concise and descriptions null unless the page provides a useful description.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'topics' => $schema->array()->max(1000)->items(
                $schema->object(fn (JsonSchema $topic): array => [
                    'code' => $topic->string()->max(100)->nullable()->required(),
                    'title' => $topic->string()->max(255)->required(),
                    'description' => $topic->string()->max(2000)->nullable()->required(),
                    'parent_index' => $topic->integer()->min(0)->nullable()->required(),
                    'source_pages' => $topic->array()->min(1)->max(20)->items(
                        $topic->object(fn (JsonSchema $source): array => [
                            'source_file_id' => $source->integer()->min(1)->required(),
                            'page_number' => $source->integer()->min(1)->required(),
                        ]),
                    )->required(),
                ]),
            )->required(),
        ];
    }
}
