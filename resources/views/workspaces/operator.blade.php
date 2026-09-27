@extends('components.app-shell')

@section('title', 'Dashboard Operator')
@section('page-title', 'Dashboard')
@section('page-description', 'Entri data dan tugas yang menunggu')

@section('content')
<div class="space-y-6">

    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Draft" :value="$metrics['draft']" />
        <x-stat-card label="Terkirim" :value="$metrics['submitted']" />
        <x-stat-card label="Data belum lengkap" :value="$metrics['incomplete']" :tone="$metrics['incomplete'] > 0 ? 'warning' : 'default'" />
        <x-stat-card label="Belum ada kelas" :value="$metrics['unplaced']" :tone="$metrics['unplaced'] > 0 ? 'warning' : 'default'" />
    </section>

    <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
        <header class="flex items-center justify-between gap-3 border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
            <h2 class="text-body font-semibold">Pendaftaran tersimpan sebagai draft</h2>
            @can('registration.create')
                <a href="{{ route('daftar') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">+ Tambah</a>
            @endcan
        </header>

        @forelse ($drafts as $reg)
            <div class="flex items-center gap-3 border-b border-[var(--app-border)] px-4 py-3 last:border-0 hover:bg-[var(--app-surface-alt)] sm:px-5">
                <div class="min-w-0 flex-1">
                    <p class="truncate text-small font-semibold">{{ $reg->student?->full_name }}</p>
                    <p class="text-caption text-[var(--app-text-muted)]">
                        {{ $reg->completeness }}% lengkap · diperbarui {{ $reg->updated_at->diffForHumans() }}
                    </p>
                </div>
                <x-status-badge :status="$reg->status" />
            </div>
        @empty
            <x-empty-state
                icon="inbox"
                title="Tidak ada draft"
                description="Semua pendaftaran sudah diteruskan ke tahap berikutnya." />
        @endforelse
    </section>
</div>
@endsection
