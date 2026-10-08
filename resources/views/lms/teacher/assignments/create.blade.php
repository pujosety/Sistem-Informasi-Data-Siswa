@extends('components.app-shell')

@section('title', 'LMS · Tugas Baru')
@section('page-title', 'Buat Tugas')
@section('page-description', $course->title)

@section('content')
<form method="POST" action="{{ route('lms.teacher.assignments.store', $course) }}" class="max-w-3xl space-y-5">
    @csrf
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Judul tugas" required />
            <x-form-field name="description" label="Instruksi" type="textarea" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form-field name="starts_at" label="Mulai" type="datetime-local" />
                <x-form-field name="due_at" label="Batas waktu" type="datetime-local" />
                <x-form-field name="max_score" label="Nilai maksimal" type="number" value="100" min="1" required />
                <x-form-field name="max_attempts" label="Maksimum percobaan" type="number" value="1" min="1" max="10" required />
                <x-form-field name="submission_type" label="Jenis pengumpulan" type="select" required>
                    <option value="text">Teks</option>
                    <option value="file">File</option>
                    <option value="text_file" selected>Teks + File</option>
                </x-form-field>
                <label class="flex items-center gap-2 self-end pb-2 text-small text-[var(--app-text)]">
                    <input type="checkbox" name="allow_late" value="1" class="rounded border-[var(--app-border-strong)]">
                    Izinkan pengumpulan terlambat
                </label>
            </div>
        </div>
    </x-card>
    <div class="flex justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Draft</button>
    </div>
</form>
@endsection
