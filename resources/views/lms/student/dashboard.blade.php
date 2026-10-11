@extends('components.app-shell')

@section('title', 'LMS · Belajar')
@section('page-title', 'Lanjutkan Belajar')
@section('page-description', 'Kelas dan materi pembelajaran yang sedang Anda ikuti')

@section('content')
<div class="space-y-5">
    <x-card title="Kelas Saya" icon="book-open" description="Course aktif yang sudah Anda ikuti">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($courses as $enrollment)
                @php
                    $course = $enrollment->course;
                    $lessons = $course?->lessons ?? collect();
                    $lessonTotal = (int) ($course?->published_lessons_count ?? $lessons->count());
                    $done = (int) ($course?->completed_lessons_count ?? 0);
                    $progress = $lessonTotal > 0 ? (int) round(($done / $lessonTotal) * 100) : 0;
                @endphp
                <a href="{{ route('lms.student.courses.show', $course) }}" class="block">
                    <x-card class="h-full transition hover:-translate-y-0.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-caption text-[var(--app-text-muted)]">{{ $course?->subject?->name ?? 'Mata pelajaran' }}</p>
                                <h2 class="mt-1 text-h3 font-semibold text-[var(--app-text)]">{{ $course?->title }}</h2>
                            </div>
                            <x-icon name="arrow-up-right" class="h-4 w-4 shrink-0 text-[var(--app-text-subtle)]" />
                        </div>
                        <p class="mt-3 text-small text-[var(--app-text-muted)]">{{ $course?->classroom?->name }} · {{ $course?->semester?->displayLabel() }}</p>
                        <div class="mt-5">
                            <div class="flex items-center justify-between text-caption">
                                <span class="text-[var(--app-text-muted)]">Progress</span>
                                <strong class="text-[var(--app-primary)]">{{ $progress }}%</strong>
                            </div>
                            <div class="progress-track mt-2" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progress {{ $course?->title }} {{ $progress }} persen">
                                <div class="progress-fill" style="width: {{ max(0, $progress) }}%"></div>
                            </div>
                        </div>
                        <p class="mt-4 text-caption font-semibold text-[var(--app-primary)]">Buka pembelajaran →</p>
                    </x-card>
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-empty-state icon="book-open" title="Belum ada kelas" message="Anda belum terdaftar pada course pembelajaran." />
                </div>
            @endforelse
        </div>
    </x-card>

    <div>{{ $courses->links() }}</div>
</div>
@endsection
