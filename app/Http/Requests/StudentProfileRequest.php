<?php

namespace App\Http\Requests;

use App\Models\Registration;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->user()?->student;

        if (! $student) {
            return false;
        }

        $registration = $student->registration()->first();

        // No registration yet (account just created, year data missing) means
        // there is nothing to lock, so allow the edit.
        if (! $registration) {
            return true;
        }

        return $registration->isEditableByStudent();
    }

    public function rules(): array
    {
        $studentId = $this->user()?->student?->id;

        return [
            'full_name' => ['required', 'string', 'max:150'],
            'nisn' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/', Rule::unique('students', 'nisn')->ignore($studentId)],
            'nik' => ['nullable', 'string', 'size:16', 'regex:/^\d{16}$/'],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_place' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'religion' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s]+$/'],
            'address' => ['required', 'string', 'max:1000'],
            'village' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'previous_school' => ['required', 'string', 'max:150'],
            // `between:min,max` on a *string* field is interpreted as a string
            // LENGTH check, so 'between:1990,2026' failed with "must be between
            // 1990 and 2026 characters". Rule::numeric()->between() is a real range.
            'graduation_year' => ['required', 'digits:4', Rule::numeric()->between(1990, (int) date('Y'))],
            'diploma_number' => ['nullable', 'string', 'max:60'],
            'previous_score' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function messages(): array
    {
        return [
            'nisn.size' => 'NISN harus terdiri dari tepat 10 angka.',
            'nisn.regex' => 'NISN hanya boleh berisi angka.',
            'nik.size' => 'NIK harus terdiri dari tepat 16 angka.',
            'nik.regex' => 'NIK hanya boleh berisi angka.',
            'birth_date.before' => 'Tanggal lahir tidak boleh di masa depan.',
            'phone.regex' => 'Nomor HP hanya boleh berisi angka, spasi, atau tanda + dan -.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if ($this->filled('nisn')) {
            $merge['nisn'] = preg_replace('/\D/', '', (string) $this->input('nisn'));
        }

        if ($this->filled('nik')) {
            $merge['nik'] = preg_replace('/\D/', '', (string) $this->input('nik'));
        }

        if ($this->filled('email')) {
            $merge['email'] = mb_strtolower(trim((string) $this->input('email')));
        }

        $this->merge($merge);
    }
}
