<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgramFileUploadRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'original_filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', Rule::in(['application/pdf', 'application/epub+zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])],
            'extension' => ['required', Rule::in(['pdf', 'docx', 'epub'])],
            'file_size' => ['required', 'integer', 'min:1', 'max:104857600'],
            'metadata' => ['nullable', 'array'],
            'metadata.author' => ['nullable', 'string', 'max:255'],
            'metadata.publisher' => ['nullable', 'string', 'max:255'],
            'metadata.edition' => ['nullable', 'string', 'max:255'],
            'metadata.publication_date' => ['nullable', 'date'],
            'metadata.effective_date' => ['nullable', 'date'],
            'metadata.rights_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $types = ['pdf' => 'application/pdf', 'epub' => 'application/epub+zip', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];

            if (isset($types[$this->input('extension')]) && $types[$this->input('extension')] !== $this->input('mime_type')) {
                $validator->errors()->add('mime_type', 'The MIME type must match the selected file extension.');
            }
        }];
    }
}
