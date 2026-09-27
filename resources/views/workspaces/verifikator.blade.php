@extends('components.app-shell')

@section('title', 'Antrean Verifikasi')
@section('page-title', 'Antrean Verifikasi')
@section('page-description', $metrics['pending'] > 0 ? $metrics['pending'].' pendaftaran menunggu' : 'Semua verifikasi selesai')

@section('content')
<div class="space-y-6">

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Menunggu" :value="$metrics['pending']" :tone="$metrics['pending'] > 0 ? 'warning' : 'success'" />
        <x-stat-card label="Perlu Perbaikan" :value="$metrics['revision']" />
        <x-stat-card label="Selesai Hari Ini" :value="$metrics['doneToday']" tone="success" />
        <x-stat-card label="Terlama (hari)" :value="$metrics['oldestDays']" :tone="$metrics['oldestDays'] >= 3 ? 'danger' : 'neutral'" />
    </section>

    @if ($metrics['pending'] === 0)
        <x-empty-state
            icon="circle-check"
            title="Antrean kosong"
            description="Tidak ada pendaftaran yang menunggu verifikasi. Kerja bagus." />
    @else
        <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Menunggu verifikasi</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Paling lama diantarkan di atas.</p>
            </header>

            <ul class="divide-y divide-[var(--app-border)]">
                @foreach ($queue as $reg)
                    <li>
                        <a href="{{ route('admin.registrations.show', $reg->student_id) }}"
                           class="flex items-center gap-3 px-4 py-3.5 hover:bg-[var(--app-surface-alt)] sm:px-5">
                            <span class="grid place-items-center w-10 h-10 shrink-0 rounded-full bg-[var(--app-surface-alt)] text-small font-bold">
                                {{ strtoupper(mb_substr($reg->student?->full_name ?? '?', 0, 2)) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-small font-semibold">{{ $reg->student?->full_name }}</p>
                                <p class="text-caption text-[var(--app-text-muted)]">
                                    {{ $reg->student?->nisn }} · dikirim {{ $reg->submitted_at?->diffForHumans() ?? '—' }}
                                </p>
                            </div>
                            <div class="hidden sm:block w-28">
                                <div class="h-1.5 rounded-full bg-[var(--app-surface-alt)] overflow-hidden">
                                    <div class="h-full rounded-full bg-[var(--app-primary)]" style="width: {{ $reg->completeness }}%"></div>
                                </div>
                                <p class="mt-1 text-caption text-[var(--app-text-muted)] text-right">{{ $reg->completeness }}%</p>
                            </div>
                            <x-status-badge :status="$reg->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
