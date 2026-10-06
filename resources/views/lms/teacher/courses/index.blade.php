@extends('components.app-shell')

@section('title', 'LMS · Course')
@section('page-title', 'Ruang Pembelajaran')
@section('page-description', 'Kelola course, kelas, dan materi pembelajaran')

@section('page-actions')
    @can('lms.course.create')
        <a href="{{ route('lms.teacher.courses.create') }}" class="btn btn-primary">Buat Course</a>
    @endcan
@endsection

@section('content')
@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif

<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @forelse ($courses as $course)
        <a href="{{ route('lms.teacher.courses.show', $course) }}" class="block">
            <x-card class="h-full transition hover:-translate-y-0.5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                        <h2 class="mt-1 text-h3 font-semibold text-[var(--app-text)]">{{ $course->title }}</h2>
                    </div>
                    <span class="badge {{ $course->status === 'published' ? 'badge-success' : 'badge-neutral' }}">
                        {{ $course->status === 'published' ? 'Terbit' : 'Draft' }}
                    </span>
                </div>
                <p class="mt-4 text-body text-[var(--app-text-muted)]">
                    {{ $course->subject?->name ?? 'Mata pelajaran' }} · {{ $course->classroom?->name ?? 'Kelas' }}
                </p>
                <p class="mt-1 text-caption text-[var(--app-text-subtle)]">
                    {{ $course->semester?->displayLabel() ?? 'Semester' }}
                </p>
            </x-card>
        </a>
    @empty
        <x-card class="md:col-span-2 xl:col-span-3">
            <x-empty-state title="Belum ada course" message="Buat course pertama untuk mulai menyiapkan pembelajaran." />
        </x-card>
    @endforelse
</div>

<div class="mt-5">{{ $courses->links() }}</div>
@endsection
