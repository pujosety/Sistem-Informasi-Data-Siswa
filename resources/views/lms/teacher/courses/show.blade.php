@extends('components.app-shell')

@section('title', 'LMS · '.$course->title)
@section('page-title', $course->title)
@section('page-description', $course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<div class="space-y-4">
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                <h2 class="mt-1 text-h2 font-semibold text-[var(--app-text)]">{{ $course->title }}</h2>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">{{ $course->description ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <span class="badge {{ $course->status === 'published' ? 'badge-success' : 'badge-neutral' }}">
                {{ $course->status === 'published' ? 'Terbit' : 'Draft' }}
            </span>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div><p class="text-caption text-[var(--app-text-muted)]">Kelas</p><p class="font-semibold">{{ $course->classroom?->name }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Semester</p><p class="font-semibold">{{ $course->semester?->displayLabel() }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Materi</p><p class="font-semibold">{{ $course->lessons->count() }} lesson</p></div>
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Materi pembelajaran</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Lesson akan muncul di portal siswa setelah diterbitkan.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-neutral">{{ $course->lessons->count() }} materi</span>
                @can('lms.lesson.manage')
                    <a href="{{ route('lms.teacher.lessons.create', $course) }}" class="btn btn-primary btn-sm">Tambah materi</a>
                @endcan
            </div>
        </div>
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->lessons as $lesson)
                <div class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <p class="font-medium">{{ $lesson->position }}. {{ $lesson->title }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $lesson->status === 'published' ? 'Terbit' : 'Draft' }}</p>
                    </div>
                    @can('lms.lesson.manage')
                        <div class="flex items-center gap-2">
                            <a href="{{ route('lms.teacher.lessons.edit', [$course, $lesson]) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @if ($lesson->status !== 'published')
                                <form method="POST" action="{{ route('lms.teacher.lessons.publish', [$course, $lesson]) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm">Terbitkan</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada materi. Tambahkan materi pertama untuk course ini.</p>
            @endforelse
        </div>
    </x-card>
</div>
@endsection
