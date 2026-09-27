@extends('components.app-shell')

@section('title', 'Anak Saya')
@section('page-title', 'Anak Saya')
@section('page-description', $rows->count().' anak terdaftar di akun ini')

@section('content')
<div class="space-y-4">

    @forelse ($rows as $row)
        @php $s = $row['student']; @endphp
        <article class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="flex items-center gap-3 p-4 sm:p-5">
                <span class="grid place-items-center w-11 h-11 shrink-0 rounded-full bg-[var(--app-surface-alt)] text-small font-bold">
                    {{ strtoupper(mb_substr($s->full_name, 0, 2)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate text-body font-semibold">{{ $s->full_name }}</h2>
                    <p class="text-small text-[var(--app-text-secondary)]">
                        {{ $row['classroom']?->name ?? 'Belum ada kelas' }}
                        @if ($row['year']) · {{ $row['year']->name }} @endif
                    </p>
                </div>
                @if ($row['status'])
                    <x-status-badge :status="$row['status']" />
                @endif
            </header>

            <dl class="grid grid-cols-2 border-t border-[var(--app-border)] sm:grid-cols-3">
                <div class="px-4 py-3 sm:px-5">
                    <dt class="text-caption text-[var(--app-text-muted)]">Kehadiran</dt>
                    <dd class="mt-0.5 text-body font-semibold tabular-nums">
                        {{ $row['attendance'] !== null ? $row['attendance'].'%' : '—' }}
                    </dd>
                </div>
                <div class="px-4 py-3 border-t border-[var(--app-border)] sm:border-t-0 sm:border-l sm:px-5">
                    <dt class="text-caption text-[var(--app-text-muted)]">Kelengkapan Data</dt>
                    <dd class="mt-0.5 text-body font-semibold tabular-nums">
                        {{ $row['completeness'] !== null ? $row['completeness'].'%' : '—' }}
                    </dd>
                </div>
                <div class="col-span-2 px-4 py-3 border-t border-[var(--app-border)] sm:col-span-1 sm:border-l sm:px-5">
                    <dt class="text-caption text-[var(--app-text-muted)]">Status</dt>
                    <dd class="mt-0.5 text-body font-semibold">
                        {{ $row['status'] ? ucfirst(str_replace('_', ' ', $row['status'])) : 'Belum mendaftar' }}
                    </dd>
                </div>
            </dl>

            <footer class="flex flex-wrap gap-2 border-t border-[var(--app-border)] p-4 sm:px-5">
                <a href="{{ route('parent.attendance', $s) }}"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] bg-[var(--app-primary)] px-3.5 py-2 text-small font-semibold text-white">
                    <x-icon name="calendar-check" class="w-4 h-4" /> Lihat Anak
                </a>
                <a href="{{ route('parent.academic', $s) }}"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-[var(--app-border)] px-3.5 py-2 text-small font-semibold">
                    Akademik
                </a>
                <a href="{{ route('parent.announcements', $s) }}"
                   class="inline-flex min-h-[44px] items-center gap-2 rounded-[var(--radius-md)] border border-[var(--app-border)] px-3.5 py-2 text-small font-semibold">
                    Pengumuman
                </a>
            </footer>
        </article>
    @empty
        <x-empty-state
            icon="users-round"
            title="Belum ada anak terdaftar"
            description="Hubungi administrator sekolah untuk menautkan akun Anda dengan data anak." />
    @endforelse
</div>
@endsection
