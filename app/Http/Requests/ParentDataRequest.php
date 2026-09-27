<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParentDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->student?->registration?->isEditableByStudent() ?? false;
    }

    public function rules(): array
    {
        return [
            'father_name' => ['required', 'string', 'max:150'],
            'father_job' => ['nullable', 'string', 'max:100'],
            'father_phone' => ['required', 'string', 'max:25'],
            'father_nik' => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/'],
            'mother_name' => ['required', 'string', 'max:150'],
            'mother_job' => ['nullable', 'string', 'max:100'],
            'mother_phone' => ['required', 'string', 'max:25'],
            'mother_nik' => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_job' => ['nullable', 'string', 'max:100'],
            'guardian_phone' => ['nullable', 'string', 'max:25'],
            'parent_address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'father_nik.regex' => 'NIK ayah hanya boleh berisi angka.',
            'mother_nik.regex' => 'NIK ibu hanya boleh berisi angka.',
            'father_nik.size' => 'NIK ayah harus 16 digit.',
            'mother_nik.size' => 'NIK ibu harus 16 digit.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['father_nik', 'mother_nik'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = preg_replace('/\D/', '', (string) $this->input($field));
            }
        }

        $this->merge($merge);
    }
}
