<?php

namespace App\Http\Requests;

use App\ProgramFileType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramFileRequest extends FormRequest
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
            'file_type' => ['required', Rule::enum(ProgramFileType::class)],
            'title' => ['required', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'metadata.author' => ['nullable', 'string', 'max:255'],
            'metadata.publisher' => ['nullable', 'string', 'max:255'],
            'metadata.edition' => ['nullable', 'string', 'max:255'],
            'metadata.publication_date' => ['nullable', 'date'],
            'metadata.effective_date' => ['nullable', 'date'],
            'metadata.rights_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
