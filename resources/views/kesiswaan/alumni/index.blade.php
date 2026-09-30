@extends('components.app-shell')

@section('title', 'Alumni')
@section('page-title', 'Alumni')
@section('page-description', $alumni->total().' siswa telah lulus')

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif

{{--
    Read-only by design. An alumni row records that a student LEFT, and every
    fact on it also lives on the student record; the graduation itself is
    written by the academic flow that closed the enrollment. Editing it here
    would create a second way to change a student's history with none of the
    consistency.
--}}
<x-alert variant="info" class="mb-4"
         title="Daftar ini hanya dibaca"
         message="Data kelulusan dicatat oleh proses akademik yang menutup enrollment. Di sini Anda bisa mencari dan membuka riwayat alumni, tetapi tidak mengubahnya." />

<form method="GET" class="surface p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
    <div class="lg:col-span-2">
        <label for="q" class="sr-only">Cari alumni</label>
        <div class="relative">
            <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
            <input id="q" name="q" value="{{ $q }}" class="field pl-9" placeholder="Cari nama atau NISN">
        </div>
    </div>
    <div>
        <label for="year" class="sr-only">Tahun lulus</label>
        <select id="year" name="year" class="field">
            <option value="">Semua tahun</option>
            @foreach ($years as $year)
                <option value="{{ $year }}" @selected((string) ($filters['year'] ?? '') === (string) $year)>
                    {{ $year }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="department_id" class="sr-only">Jurusan</label>
        <select id="department_id" name="department_id" class="field">
            <option value="">Semua jurusan</option>
            @foreach ($departments as $id => $name)
                <option value="{{ $id }}" @selected((int) ($filters['department_id'] ?? 0) === $id)>
                    {{ $name }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary flex-1 justify-center" type="submit">Filter</button>
        <a href="{{ route('alumni.index') }}" class="btn btn-secondary shrink-0" aria-label="Reset">
            <x-icon name="refresh-cw" class="w-4 h-4" />
        </a>
    </div>
</form>

@forelse ($alumni as $row)
    <div class="surface p-4 mb-3">
        <div class="flex flex-wrap items-center gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('alumni.show', $row) }}"
                       class="font-semibold text-[var(--app-text)] hover:underline">
                        {{ $row->student?->full_name ?? 'Siswa tanpa akun' }}
                    </a>
                    <span class="badge badge-success">
                        <x-icon name="check-circle" class="w-3 h-3" />
                        Lulus {{ $row->graduation_year }}
                    </span>
                </div>

                <p class="text-small text-[var(--app-text-muted)] mt-1">
                    {{ $row->lastClassroom?->name ?? 'Kelas tidak tercatat' }}
                    @if ($row->department) · {{ $row->department->name }} @endif
                </p>

                <p class="text-caption text-[var(--app-text-subtle)] mt-1">
                    @if ($row->student?->nisn) NISN {{ $row->student->nisn }} @endif
                    @if ($row->graduation_date)
                        · {{ $row->graduation_date->format('d F Y') }}
                    @endif
                </p>
            </div>

            <a href="{{ route('alumni.show', $row) }}" class="btn btn-secondary shrink-0">
                <x-icon name="eye" class="w-4 h-4" />
                Detail
            </a>
        </div>
    </div>
@empty
    <x-card>
        <x-empty-state icon="award"
                       title="Belum ada alumni"
                       description="Siswa yang tercatat sudah lulus akan muncul di sini beserta riwayat kelasnya." />
    </x-card>
@endforelse

@if ($alumni->hasPages())
    <div class="mt-4">{{ $alumni->links() }}</div>
@endif
@endsection
