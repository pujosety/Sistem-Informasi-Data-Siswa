@extends('components.app-shell')

@section('title', 'Absensi '.$student->full_name)
@section('page-title', 'Absensi')
@section('page-description', $student->full_name . ($enrollment?->classroom ? ' · '.$enrollment->classroom->name : ''))

@section('content')
<div class="space-y-5 max-w-3xl">

    @if (! $enrollment)
        <x-empty-state
            icon="calendar-x"
            title="Belum ada data absensi"
            description="Siswa belum ditempatkan pada kelas aktif, sehingga absensi belum dapat ditampilkan." />
    @else
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-stat-card label="Total Catatan" :value="$summary['total']" />
            <x-stat-card label="Kehadiran"
                         :value="$summary['rate'] !== null ? $summary['rate'].'%' : '—'"
                         tone="success" />
            <x-stat-card label="Kelas" :value="$enrollment->classroom?->name ?? '—'" />
        </section>

        <section class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Ringkasan kehadiran</h2>
            </header>
            <ul class="divide-y divide-[var(--app-border)]">
                @foreach (\App\Models\AttendanceRecord::STATUSES as $value => $label)
                    <li class="flex items-center justify-between px-4 py-3 sm:px-5">
                        <span class="text-small text-[var(--app-text-secondary)]">{{ $label }}</span>
                        <span class="text-small font-semibold tabular-nums">{{ $summary['counts'][$value] ?? 0 }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <p class="text-caption text-[var(--app-text-muted)]">
            Data kehadiran diperbarui oleh wali kelas. Koreksi dilakukan melalui sekolah.
        </p>
    @endif
</div>
@endsection
