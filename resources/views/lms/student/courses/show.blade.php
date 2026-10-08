@extends('components.app-shell')

@section('title', 'LMS · '.$course->title)
@section('page-title', $course->title)
@section('page-description', ($course->subject?->name ?? 'Mata pelajaran').' · '.($course->classroom?->name ?? 'Kelas'))

@section('content')
<div class="space-y-5">
    @if (session('success'))
        <x-alert variant="success" :message="session('success')" />
    @endif

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                <h2 class="mt-1 text-h2 font-semibold text-[var(--app-text)]">{{ $course->title }}</h2>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">{{ $course->description ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <span class="badge badge-success">Sedang diikuti</span>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div><p class="text-caption text-[var(--app-text-muted)]">Kelas</p><p class="font-semibold">{{ $course->classroom?->name }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Semester</p><p class="font-semibold">{{ $course->semester?->displayLabel() }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Guru</p><p class="font-semibold">{{ $course->teacher?->name ?? 'Guru pengampu' }}</p></div>
        </div>
    </x-card>

    <x-card title="Materi Pembelajaran" icon="book-open" description="Selesaikan materi satu per satu untuk memperbarui progress Anda.">
        <div class="space-y-3">
            @forelse ($course->lessons as $lesson)
                @php($completed = in_array($lesson->id, $completedLessonIds, true))
                <article class="flex flex-col gap-3 rounded-[var(--radius-md)] border border-[var(--app-border)] p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $completed ? 'bg-[var(--app-success-soft)] text-[var(--app-success)]' : 'bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]' }}">
                            @if ($completed)
                                <x-icon name="check" class="h-4 w-4" />
                            @else
                                <span class="text-small font-bold">{{ $lesson->position }}</span>
                            @endif
                        </span>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-[var(--app-text)]">{{ $lesson->title }}</h3>
                            @if ($lesson->module)
                                <p class="mt-1 text-caption font-medium text-[var(--app-primary)]">{{ $lesson->module->title }}</p>
                            @endif
                            @if ($lesson->body)
                                <p class="mt-1 line-clamp-2 text-small text-[var(--app-text-muted)]">{{ $lesson->body }}</p>
                            @endif
                            @if ($lesson->external_url || $lesson->video_url)
                                <div class="mt-2 flex flex-wrap gap-3 text-caption font-semibold">
                                    @if ($lesson->external_url)
                                        <a href="{{ $lesson->external_url }}" target="_blank" rel="noopener noreferrer" class="text-[var(--app-primary)]">Buka materi ↗</a>
                                    @endif
                                    @if ($lesson->video_url)
                                        <a href="{{ $lesson->video_url }}" target="_blank" rel="noopener noreferrer" class="text-[var(--app-primary)]">Tonton video ↗</a>
                                    @endif
                                </div>
                            @endif
                            <p class="mt-2 text-caption {{ $completed ? 'text-[var(--app-success)]' : 'text-[var(--app-text-subtle)]' }}">{{ $completed ? 'Selesai' : 'Belum selesai' }}</p>
                        </div>
                    </div>
                    @if (! $completed)
                        @can('lms.lesson.complete')
                            <form method="POST" action="{{ route('lms.student.lessons.complete', [$course, $lesson]) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="btn btn-primary btn-sm">Tandai selesai</button>
                            </form>
                        @endcan
                    @else
                        <span class="badge badge-success shrink-0">Selesai</span>
                    @endif
                </article>
            @empty
                <x-empty-state icon="book-open" title="Belum ada materi" message="Guru belum menerbitkan materi untuk course ini." />
            @endforelse
        </div>
    </x-card>
</div>
@endsection
