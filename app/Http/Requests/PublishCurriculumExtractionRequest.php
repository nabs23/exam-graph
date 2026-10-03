<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishCurriculumExtractionRequest extends FormRequest
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
        return [
            'subjects' => ['required', 'array', 'min:1', 'max:100'],
            'subjects.*.include' => ['required', 'boolean'],
            'subjects.*.name' => ['nullable', 'string', 'max:255'],
            'subjects.*.code' => ['nullable', 'string', 'max:50'],
            'subjects.*.description' => ['nullable', 'string', 'max:2000'],
            'subjects.*.merge_target_id' => ['nullable', 'integer', 'min:1'],
            'subjects.*.merge_source_ids' => ['sometimes', 'array', 'max:100'],
            'subjects.*.merge_source_ids.*' => ['integer', 'distinct', 'min:1'],
            'subjects.*.topics' => ['sometimes', 'array', 'max:500'],
            'subjects.*.topics.*.include' => ['required', 'boolean'],
            'subjects.*.topics.*.title' => ['nullable', 'string', 'max:255'],
            'subjects.*.topics.*.code' => ['nullable', 'string', 'max:50'],
            'subjects.*.topics.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
