@extends('components.app-shell')

@section('title', 'Dashboard Kesiswaan')
@section('page-title', 'Dashboard')
@section('page-description', 'Siswa, kelas, dan siklus akademik')

@section('page-actions')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('kesiswaan.students') }}"
           class="inline-flex items-center gap-2 rounded-[var(--radius-md)] bg-[var(--app-primary)] px-3.5 py-2 text-small font-semibold text-white">
            <x-icon name="search" class="w-4 h-4" /> Cari Siswa
        </a>
        @can('classroom.view')
            <a href="{{ route('academic.classes.index') }}"
               class="inline-flex items-center gap-2 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] px-3.5 py-2 text-small font-semibold">
                <x-icon name="users-round" class="w-4 h-4" /> Lihat Kelas
            </a>
        @endcan
    </div>
@endsection

@section('content')
<div class="space-y-6">

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Siswa Aktif" :value="$metrics['active']" />
        <x-stat-card label="Kelas" :value="$metrics['classes']" :hint="$year?->name" />
        <x-stat-card label="Belum ada kelas" :value="$metrics['unplaced']" :tone="$metrics['unplaced'] > 0 ? 'warning' : 'neutral'" />
        <x-stat-card label="Data belum lengkap" :value="$metrics['incomplete']" :tone="$metrics['incomplete'] > 0 ? 'warning' : 'neutral'" />
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Siswa per tingkat</h2>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $year?->name ?? 'Tahun ajaran aktif' }}</p>
            </header>
            <div class="p-4 sm:p-5">
                @php $max = max(1, (int) ($byLevel->max('live_enrollments_count') ?? 1)); @endphp
                <ul class="space-y-3">
                    @forelse ($byLevel as $row)
                        <li>
                            <div class="flex items-center justify-between text-small mb-1.5">
                                <span class="font-medium">Tingkat {{ $row->level }}</span>
                                <span class="tabular-nums text-[var(--app-text-secondary)]">
                                    {{ $row->live_enrollments_count }} siswa · {{ $row->c }} kelas
                                </span>
                            </div>
                            <div class="h-2 rounded-full bg-[var(--app-surface-alt)] overflow-hidden">
                                <div class="h-full rounded-full bg-[var(--app-primary)]"
                                     style="width: {{ round(($row->live_enrollments_count / $max) * 100) }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="py-6 text-center text-small text-[var(--app-text-muted)]">Belum ada kelas pada tahun ajaran ini.</li>
                    @endforelse
                </ul>
            </div>
        </section>

        <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Kelas per jurusan</h2>
            </header>
            <ul class="divide-y divide-[var(--app-border)]">
                @forelse ($byDepartment as $d)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0">
                            <p class="truncate text-small font-medium">{{ $d->name }}</p>
                            <p class="text-caption text-[var(--app-text-muted)]">{{ $d->code }}</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-[var(--app-surface-alt)] px-2.5 py-1 text-caption font-semibold tabular-nums">
                            {{ $d->c }} kelas
                        </span>
                    </li>
                @empty
                    <li class="px-4 py-8 text-center text-small text-[var(--app-text-muted)]">Belum ada jurusan.</li>
                @endforelse
            </ul>
        </section>
    </div>
</div>
@endsection
