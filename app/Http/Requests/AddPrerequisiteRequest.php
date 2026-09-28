<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPrerequisiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prerequisite_concept_id' => ['required', 'exists:concepts,id'],
        ];
    }
}
