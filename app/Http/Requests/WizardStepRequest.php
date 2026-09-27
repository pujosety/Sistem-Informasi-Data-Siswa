<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One endpoint, seven steps. Rules per step are declared together and then
 * filtered to the step actually being saved, so the wizard can post any step
 * without the remaining steps' (empty) fields failing validation.
 */
class WizardStepRequest extends FormRequest
{
    public const STEPS = ['akun', 'pribadi', 'orang-tua', 'pendidikan', 'dokumen', 'review'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->input('step');
        $studentId = $this->user()?->student?->id;
        $all = $this->allRules($studentId);

        return array_filter(
            $all,
            fn ($key) => $this->fieldsFor($step) === ['*'] || in_array($key, $this->fieldsFor($step), true),
            ARRAY_FILTER_USE_KEY
        );
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
            'father_name.required' => 'Nama ayah wajib diisi.',
            'mother_name.required' => 'Nama ibu wajib diisi.',
            'previous_school.required' => 'Asal sekolah wajib diisi.',
        ];
    }

    /** @return array<int,string> */
    private function fieldsFor(?string $step): array
    {
        return match ($step) {
            'akun' => ['name', 'email'],
            'pribadi' => ['full_name', 'nisn', 'nik', 'gender', 'birth_place', 'birth_date', 'religion', 'phone', 'address', 'village', 'district', 'city', 'province', 'postal_code'],
            'orang-tua' => ['father_name', 'father_job', 'father_phone', 'father_nik', 'mother_name', 'mother_job', 'mother_phone', 'mother_nik', 'guardian_name', 'guardian_job', 'guardian_phone', 'parent_address'],
            'pendidikan' => ['previous_school', 'graduation_year', 'diploma_number', 'previous_score'],
            'dokumen' => ['file'],
            'review' => ['*'],
            default => ['*'],
        };
    }

    private function allRules(?int $studentId): array
    {
        return [
            // Akun
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'string', 'email', 'max:190'],

            // Pribadi
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

            // Orang tua
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

            // Pendidikan
            'previous_school' => ['required', 'string', 'max:150'],
            // See StudentProfileRequest: a string `between` is a length check.
            'graduation_year' => ['required', 'digits:4', Rule::numeric()->between(1990, (int) date('Y'))],
            'diploma_number' => ['nullable', 'string', 'max:60'],
            'previous_score' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['nisn', 'nik', 'father_nik', 'mother_nik'] as $field) {
            if ($this->filled($field)) {
                $merge[$field] = preg_replace('/\D/', '', (string) $this->input($field));
            }
        }

        $this->merge($merge);
    }
}
