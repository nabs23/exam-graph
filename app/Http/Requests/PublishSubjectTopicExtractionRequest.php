<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PublishSubjectTopicExtractionRequest extends FormRequest
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
            'topics' => ['required', 'array', 'min:1', 'max:1000'],
            'topics.*.include' => ['required', 'boolean'],
            'topics.*.title' => ['nullable', 'string', 'max:255'],
            'topics.*.code' => ['nullable', 'string', 'max:100'],
            'topics.*.description' => ['nullable', 'string', 'max:2000'],
            'topics.*.parent_index' => ['nullable', 'integer', 'min:0'],
            'topics.*.parent_reference' => ['nullable', 'string', 'regex:/^(proposal:0|proposal:[1-9][0-9]*|existing:[1-9][0-9]*)$/', 'max:30'],
            'topics.*.match_existing_topic_id' => ['nullable', 'integer', 'min:1'],
            'topics.*.sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
