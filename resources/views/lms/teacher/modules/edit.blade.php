@extends('components.app-shell')

@section('title', 'LMS · Edit Bab')
@section('page-title', 'Edit Bab Pembelajaran')
@section('page-description', $course->title.' · '.$course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<form method="POST" action="{{ route('lms.teacher.modules.update', [$course, $module]) }}" class="max-w-3xl space-y-5">
    @csrf
    @method('PUT')
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Nama bab" :value="$module->title" required />
            <x-form-field name="description" label="Deskripsi" type="textarea" rows="5" :value="$module->description" />
            <x-form-field name="position" label="Urutan" type="number" :value="$module->position" min="1" required />
        </div>
    </x-card>
    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
