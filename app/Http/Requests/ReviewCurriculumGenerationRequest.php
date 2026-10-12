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
        $isSubjectWideConceptGeneration = $isConceptGeneration && is_array($generation->scope_snapshot);
        $topicIds = collect($generation->scope_snapshot['topics'] ?? [])->pluck('id')->all();

        return [
            'proposal_hash' => ['required', 'string', 'size:64'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.draft_id' => ['required', 'string', 'max:64', 'distinct'],
            'items.*.include' => ['required', 'boolean'],
            'items.*.name' => [Rule::requiredIf($isSubjectGeneration), 'nullable', 'string', 'max:255'],
            'items.*.title' => [Rule::requiredIf(! $isSubjectGeneration), 'nullable', 'string', 'max:255'],
            'items.*.code' => ['required', 'string', 'max:100'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.parent_draft_id' => ['nullable', 'string', 'max:64'],
            'items.*.sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'items.*.syllabus_topic_id' => [Rule::prohibitedIf(! $isSubjectWideConceptGeneration), 'nullable', 'integer', Rule::in($topicIds)],
        ];
    }
}
