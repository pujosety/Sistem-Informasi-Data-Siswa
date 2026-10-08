@extends('components.app-shell')

@section('title', 'LMS · Materi Baru')
@section('page-title', 'Tambah Materi')
@section('page-description', $course->title.' · '.$course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<form method="POST" action="{{ route('lms.teacher.lessons.store', $course) }}" class="max-w-3xl space-y-5">
    @csrf
    <x-card>
        <div class="space-y-4">
            <x-form-field name="title" label="Judul materi" required placeholder="Contoh: Pengenalan Algoritma" />
            @if ($course->modules->isNotEmpty())
                <div>
                    <label for="module_id" class="mb-1.5 block text-small font-medium text-[var(--app-text)]">Bab pembelajaran</label>
                    <select id="module_id" name="module_id" class="input">
                        <option value="">Tanpa bab</option>
                        @foreach ($course->modules as $module)
                            <option value="{{ $module->id }}" @selected(old('module_id') == $module->id)>{{ $module->position }}. {{ $module->title }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <x-form-field name="body" label="Isi materi" type="textarea" rows="10" hint="Gunakan teks terstruktur yang mudah dibaca siswa." />
            <x-form-field name="position" label="Urutan" type="number" :value="$nextPosition" min="1" required hint="Urutan materi dalam course." />
        </div>
    </x-card>

    <div class="flex items-center justify-end gap-2">
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary">Batal</a>
        <button class="btn btn-primary">Simpan Draft</button>
    </div>
</form>
@endsection
