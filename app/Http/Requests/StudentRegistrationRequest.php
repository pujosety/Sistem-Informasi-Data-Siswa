<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', 'min:8', 'max:72'],
            'nisn' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/', Rule::unique('students', 'nisn')],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
            'password.min' => 'Password minimal 8 karakter.',
            'nisn.size' => 'NISN harus terdiri dari tepat 10 angka.',
            'nisn.regex' => 'NISN hanya boleh berisi angka.',
            'terms.accepted' => 'Anda harus menyetujui ketentuan pendaftaran.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'nisn' => preg_replace('/\D/', '', (string) $this->input('nisn')),
        ]);
    }
}
