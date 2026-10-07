@extends('components.app-shell')

@section('title', 'LMS · Edit Materi')
@section('page-title', 'Edit Materi')
@section('page-description', $course->title.' · '.$course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<form method="POST" action="{{ route('lms.teacher.lessons.update', [$course, $lesson]) }}" class="max-w-3xl space-y-5">
    @csrf
    @method('PUT')
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Judul materi" :value="$lesson->title" required />
            <x-form-field name="body" label="Isi materi" type="textarea" rows="10" :value="$lesson->body" />
            <x-form-field name="position" label="Urutan" type="number" :value="$lesson->position" min="1" required />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
