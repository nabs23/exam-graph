<?php

namespace App\Http\Requests;

use App\Services\SubjectTopicExtractionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectTopicExtractionRequest extends FormRequest
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
            'source_file_ids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('ai.subject_topic_extraction.max_files', 10))],
            'source_file_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'model' => ['nullable', 'string', Rule::in(app(SubjectTopicExtractionService::class)->availableModels())],
        ];
    }
}
