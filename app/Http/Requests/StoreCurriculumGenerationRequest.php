<?php

namespace App\Http\Requests;

use App\CurriculumGenerationKind;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurriculumGenerationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-content') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Program $program */
        $program = $this->route('program');
        $kind = $this->input('kind');
        $isLessonGeneration = $kind === CurriculumGenerationKind::Lessons->value;
        $requiresSubject = in_array($kind, [CurriculumGenerationKind::Topics->value, CurriculumGenerationKind::Concepts->value, CurriculumGenerationKind::Lessons->value], true);
        $isConceptGeneration = $kind === CurriculumGenerationKind::Concepts->value;
        $topicSubjectId = $this->input('subject_id');
        $models = array_values(array_unique(array_filter([
            ...config('ai.curriculum_generation.models', []),
            config('ai.curriculum_generation.model'),
        ], fn (mixed $model): bool => is_string($model) && $model !== '')));

        return [
            'kind' => ['required', Rule::enum(CurriculumGenerationKind::class)],
            'subject_id' => [
                Rule::requiredIf($requiresSubject),
                'nullable',
                'integer',
                Rule::exists('subjects', 'id')->where('program_id', $program->id),
            ],
            'syllabus_topic_id' => [
                Rule::prohibitedIf($isConceptGeneration),
                'nullable',
                'integer',
                Rule::exists('syllabus_topics', 'id')->where('subject_id', $topicSubjectId),
            ],
            'source_file_ids' => [Rule::prohibitedIf($isConceptGeneration || $isLessonGeneration), Rule::requiredIf(! $isConceptGeneration && ! $isLessonGeneration), 'nullable', 'array', 'min:1', 'max:'.max(1, (int) config('ai.curriculum_generation.max_files', 10))],
            'source_file_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('program_files', 'id')->where('program_id', $program->id)->where('upload_status', 'uploaded'),
            ],
            'request_context' => ['nullable', 'string', 'max:4000'],
            'scope_type' => [Rule::requiredIf($isLessonGeneration), Rule::prohibitedIf(! $isLessonGeneration), 'nullable', Rule::in(['concept', 'topic', 'subject'])],
            'scope_id' => [Rule::requiredIf($isLessonGeneration), Rule::prohibitedIf(! $isLessonGeneration), 'nullable', 'integer', 'min:1'],
            'concept_ids' => [Rule::requiredIf($isLessonGeneration), Rule::prohibitedIf(! $isLessonGeneration), 'nullable', 'array', 'min:1', 'max:'.max(1, (int) config('ai.curriculum_generation.max_lesson_concepts', 5))],
            'concept_ids.*' => ['required', 'integer', 'distinct', Rule::exists('concepts', 'id')->where('subject_id', $topicSubjectId)],
            'ungrounded' => [Rule::requiredIf(! $isLessonGeneration), 'boolean', Rule::prohibitedIf($isLessonGeneration)],
            'model' => ['nullable', 'string', Rule::in($models)],
        ];
    }
}
