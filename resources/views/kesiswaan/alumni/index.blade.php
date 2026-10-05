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

{{-- The same three filters and the same query parameters as before, now
     through the shared components. The "Reset" control only appears when a
     filter is actually applied, which the hand-written version could not do:
     it rendered an always-there reset button that did nothing on a fresh
     visit. --}}
<x-filter-bar>
    <div class="lg:col-span-2 min-w-[220px]">
        <x-search-input inline :value="$q" placeholder="Cari nama atau NISN" />
    </div>

    <div class="min-w-[160px]">
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

    <div class="min-w-[160px]">
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
</x-filter-bar>

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
