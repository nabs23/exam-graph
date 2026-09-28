<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'choices' => ['required', 'array', 'min:2'],
            'choices.*.id' => ['nullable', 'exists:question_choices,id'],
            'choices.*.content' => ['required', 'string'],
            'choices.*.is_correct' => ['required', 'boolean'],
        ];
    }
}
