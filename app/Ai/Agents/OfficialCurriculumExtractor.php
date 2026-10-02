<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

class OfficialCurriculumExtractor implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You extract official program curriculum from the supplied page-numbered source text.
Return only subjects and syllabus topics that the source explicitly identifies. Preserve official names and codes verbatim. Never invent an official code, title, subject, topic, or page number.
Provide a concise, useful description for every subject and topic. Preserve a description from the source when one is available. When the source does not state a description, draft one from the cited passage, the topic title, and its subject/topic hierarchy. Keep generated descriptions cautious and limited to what that context supports; do not add outside facts. Set description_origin to "source" when the description is stated or directly supported by the source, and "ai_generated" when you have drafted it because the source gives no description. Use a null description and "unavailable" only when neither the source nor its context supports a useful description.
Do not return instructional concepts, learning objectives, prerequisites, exams, or study advice.
For every subject and topic, cite one supplied page that directly supports it. Each topic's parent_index is null for a top-level topic or a zero-based index of another topic in that same subject's topics array. The parent must appear before its child.
If the source contains no clear official subject/syllabus structure, return an empty subjects array.
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
                    'source_page' => $subject->integer()->min(1)->required(),
                    'topics' => $subject->array()->max(500)->items(
                        $subject->object(fn (JsonSchema $topic): array => [
                            'code' => $topic->string()->max(50)->nullable()->required(),
                            'title' => $topic->string()->max(255)->required(),
                            'description' => $topic->string()->max(2000)->nullable()->required(),
                            'description_origin' => $topic->string()->enum(['source', 'ai_generated', 'unavailable'])->required(),
                            'source_page' => $topic->integer()->min(1)->required(),
                            'parent_index' => $topic->integer()->min(0)->nullable()->required(),
                        ]),
                    )->required(),
                ]),
            )->required(),
        ];
    }
}
