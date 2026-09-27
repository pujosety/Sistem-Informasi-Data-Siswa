<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DocumentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->student?->registration?->isEditableByStudent() ?? false;
    }

    public function rules(): array
    {
        $type = $this->route('documentType');

        $mimes = $type?->mimes() ?: 'jpg,jpeg,png,webp,pdf';
        $maxKb = $type?->max_size_kb ?: 2048;

        return [
            'file' => [
                'required',
                'file',
                'mimes:'.$mimes,
                'max:'.max(1, intdiv($maxKb, 1024)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Format berkas harus JPG, PNG, WEBP, atau PDF.',
            'file.max' => 'Ukuran berkas melebihi batas yang diperbolehkan.',
            'file.required' => 'Pilih berkas yang ingin diunggah.',
        ];
    }
}
