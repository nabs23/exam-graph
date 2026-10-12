<?php

namespace App\Http\Requests;

use App\CurriculumGenerationKind;
use App\Models\CurriculumGeneration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCurriculumGenerationRequest extends FormRequest
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
        /** @var CurriculumGeneration $generation */
        $generation = $this->route('curriculumGeneration');
        $isSubjectGeneration = $generation->kind === CurriculumGenerationKind::Subjects;
        $isConceptGeneration = $generation->kind === CurriculumGenerationKind::Concepts;
        $isLessonGeneration = $generation->kind === CurriculumGenerationKind::Lessons;
        $isSubjectWideConceptGeneration = $isConceptGeneration && is_array($generation->scope_snapshot);
        $topicIds = collect($generation->scope_snapshot['topics'] ?? [])->pluck('id')->all();

        return [
            'proposal_hash' => ['required', 'string', 'size:64'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.draft_id' => ['required', 'string', 'max:64', 'distinct'],
            'items.*.include' => ['required', 'boolean'],
            'items.*.name' => [Rule::requiredIf($isSubjectGeneration), 'nullable', 'string', 'max:255'],
            'items.*.title' => [Rule::requiredIf(! $isSubjectGeneration), 'nullable', 'string', 'max:255'],
            'items.*.code' => [Rule::requiredIf(! $isLessonGeneration), Rule::prohibitedIf($isLessonGeneration), 'nullable', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.parent_draft_id' => ['nullable', 'string', 'max:64'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'items.*.syllabus_topic_id' => [Rule::prohibitedIf(! $isSubjectWideConceptGeneration), 'nullable', 'integer', Rule::in($topicIds)],
            'items.*.summary' => [Rule::requiredIf($isLessonGeneration), 'nullable', 'string', 'max:1000'],
            'items.*.recap' => [Rule::requiredIf($isLessonGeneration), 'nullable', 'string', 'max:2000'],
            'items.*.sections' => [Rule::requiredIf($isLessonGeneration), 'array', 'min:1', 'max:'.max(1, (int) config('ai.curriculum_generation.max_lesson_sections', 5))],
            'items.*.sections.*.heading' => ['required_with:items.*.sections', 'string', 'max:255'],
            'items.*.sections.*.explanation' => ['required_with:items.*.sections', 'string', 'max:5000'],
            'items.*.sections.*.example' => ['nullable', 'array'],
            'items.*.sections.*.example.scenario' => ['nullable', 'string', 'max:1000'],
            'items.*.sections.*.example.steps' => ['nullable', 'array', 'max:10'],
            'items.*.sections.*.example.steps.*' => ['string', 'max:1000'],
            'items.*.sections.*.example.takeaway' => ['nullable', 'string', 'max:1000'],
            'items.*.common_misconceptions' => ['nullable', 'array', 'max:10'],
            'items.*.common_misconceptions.*.misconception' => ['required_with:items.*.common_misconceptions', 'string', 'max:1000'],
            'items.*.common_misconceptions.*.correction' => ['required_with:items.*.common_misconceptions', 'string', 'max:1000'],
        ];
    }
}
