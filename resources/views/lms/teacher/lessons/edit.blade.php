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
            @if ($course->modules->isNotEmpty())
                <div>
                    <label for="module_id" class="mb-1.5 block text-small font-medium text-[var(--app-text)]">Bab pembelajaran</label>
                    <select id="module_id" name="module_id" class="input">
                        <option value="">Tanpa bab</option>
                        @foreach ($course->modules as $module)
                            <option value="{{ $module->id }}" @selected(old('module_id', $lesson->module_id) == $module->id)>{{ $module->position }}. {{ $module->title }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-form-field name="body" label="Isi materi" type="textarea" rows="10" :value="$lesson->body" />
            <x-form-field name="external_url" label="Tautan materi eksternal" type="url" :value="$lesson->external_url" placeholder="https://..." />
            <x-form-field name="video_url" label="Tautan video" type="url" :value="$lesson->video_url" placeholder="https://..." />
            <x-form-field name="position" label="Urutan" type="number" :value="$lesson->position" min="1" required />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Perubahan</button>
    </div>
</form>
@endsection
