@extends('components.app-shell')

@section('title', 'LMS · Bab Baru')
@section('page-title', 'Tambah Bab Pembelajaran')
@section('page-description', $course->title.' · '.$course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<form method="POST" action="{{ route('lms.teacher.modules.store', $course) }}" class="max-w-3xl space-y-5">
    @csrf
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Nama bab" required placeholder="Contoh: Bab 1 · Pengenalan Algoritma" />
            <x-form-field name="description" label="Deskripsi" type="textarea" rows="5" hint="Ringkasan singkat yang membantu siswa memahami isi bab." />
            <x-form-field name="position" label="Urutan" type="number" :value="$nextPosition" min="1" required hint="Urutan bab dalam course." />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Bab Draft</button>
    </div>
</form>
@endsection
