@extends('components.app-shell')

@section('title', 'LMS · Pengumuman Baru')
@section('page-title', 'Tambah Pengumuman')
@section('page-description', $course->title)

@section('content')
<form method="POST" action="{{ route('lms.teacher.announcements.store', $course) }}" class="max-w-3xl space-y-5">
    @csrf
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Judul pengumuman" required placeholder="Contoh: Perubahan jadwal kuis" />
            <x-form-field name="body" label="Isi pengumuman" type="textarea" rows="10" required />
        </div>
    </x-card>
    <div class="flex justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Draft</button>
    </div>
</form>
@endsection
