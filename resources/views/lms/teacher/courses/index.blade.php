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

@if ($classroomId)
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--app-primary)]/20 bg-[var(--app-primary)]/5 p-4">
        <div>
            <p class="font-semibold text-[var(--app-text)]">Course dari Kelas Saya</p>
            <p class="text-small text-[var(--app-text-muted)]">Daftar ini difilter ke kelas yang Anda pilih. Akses kelas tetap diperiksa di server.</p>
        </div>
        <a class="btn btn-secondary btn-sm" href="{{ route('lms.teacher.courses.index') }}">Lihat semua course</a>
    </div>
@endif

<x-card title="Alur Guru" icon="book-open" description="Mulai dari course, siapkan materi dan evaluasi, lalu pantau hasil belajar.">
    <ol class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <li class="rounded-lg bg-[var(--app-surface-muted)] p-4">
            <p class="text-caption font-semibold text-[var(--app-primary)]">Buat</p>
            <p class="mt-1 font-semibold">Course dan kelas</p>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Tentukan tahun ajaran, semester, mata pelajaran, dan kelas.</p>
        </li>
        <li class="rounded-lg bg-[var(--app-surface-muted)] p-4">
            <p class="text-caption font-semibold text-[var(--app-primary)]">Susun</p>
            <p class="mt-1 font-semibold">Bab dan materi</p>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Buka course, tambah bab, isi materi, lalu terbitkan.</p>
        </li>
        <li class="rounded-lg bg-[var(--app-surface-muted)] p-4">
            <p class="text-caption font-semibold text-[var(--app-primary)]">Evaluasi</p>
            <p class="mt-1 font-semibold">Tugas dan kuis</p>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Buat evaluasi dan terbitkan agar terlihat oleh siswa.</p>
        </li>
        <li class="rounded-lg bg-[var(--app-surface-muted)] p-4">
            <p class="text-caption font-semibold text-[var(--app-primary)]">Pantau</p>
            <p class="mt-1 font-semibold">Pengumpulan dan nilai</p>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Periksa tugas masuk, beri nilai, dan lihat gradebook.</p>
        </li>
    </ol>
</x-card>

<div class="mt-4 grid min-w-0 gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
