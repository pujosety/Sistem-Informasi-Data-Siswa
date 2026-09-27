@extends('components.app-shell')

@section('title', 'Dashboard Admin')
@section('page-title', 'Dashboard')
@section('page-description', 'Administrasi harian sekolah')

@section('page-actions')
    <div class="flex flex-wrap gap-2">
        @can('student.create')
            <a href="{{ route('admin.registrations') }}"
               class="inline-flex items-center gap-2 rounded-[var(--radius-md)] bg-[var(--app-primary)] px-3.5 py-2 text-small font-semibold text-white">
                <x-icon name="user-plus" class="w-4 h-4" /> Tambah Siswa
            </a>
        @endcan
        @can('report.view')
            <a href="{{ route('laporan.index') }}"
               class="inline-flex items-center gap-2 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] px-3.5 py-2 text-small font-semibold">
                <x-icon name="file-text" class="w-4 h-4" /> Buat Laporan
            </a>
        @endcan
    </div>
@endsection

@section('content')
<div class="space-y-6">

    {{-- 1. Urgent / actionable first --------------------------------- --}}
    @if ($metrics['pending'] + $metrics['revision'] > 0)
        <section class="rounded-[var(--radius-lg)] border border-[var(--app-warning)]/30 bg-[var(--app-warning)]/5 p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-body font-semibold">Antrean perlu tindakan</h2>
                    <p class="text-small text-[var(--app-text-secondary)] mt-0.5">
                        {{ $metrics['pending'] }} menunggu verifikasi
                        @if ($metrics['revision']) · {{ $metrics['revision'] }} perlu perbaikan @endif
                    </p>
                </div>
                @can('verification.view')
                    <a href="{{ route('admin.registrations') }}"
                       class="inline-flex items-center gap-2 rounded-[var(--radius-md)] bg-[var(--app-primary)] px-3.5 py-2 text-small font-semibold text-white">
                        Buka Antrean
                        <x-icon name="arrow-right" class="w-4 h-4" />
                    </a>
                @endcan
            </div>
        </section>
    @endif

    {{-- 2. Core metrics ------------------------------------------------- --}}
    <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat-card label="Total Siswa" :value="$metrics['total']" />
        <x-stat-card label="Terverifikasi" :value="$metrics['verified']" tone="success" />
        <x-stat-card label="Menunggu Verifikasi" :value="$metrics['pending']" tone="warning" />
        <x-stat-card label="Perlu Perbaikan" :value="$metrics['revision']" tone="danger" />
    </section>

    {{-- 3. Main workflow ------------------------------------------------ --}}
    <div class="grid gap-4 lg:grid-cols-3">
        <section class="lg:col-span-2 rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="flex items-center justify-between gap-3 border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Antrean pendaftaran</h2>
                @can('verification.view')
                    <a href="{{ route('admin.registrations') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Lihat semua</a>
                @endcan
            </header>

            @forelse ($queue as $reg)
                <a href="{{ route('admin.registrations.show', $reg->student_id) }}"
                   class="flex items-center gap-3 border-b border-[var(--app-border)] px-4 py-3 last:border-0 hover:bg-[var(--app-surface-alt)] sm:px-5">
                    <span class="grid place-items-center w-9 h-9 shrink-0 rounded-full bg-[var(--app-surface-alt)] text-caption font-bold">
                        {{ strtoupper(mb_substr($reg->student?->full_name ?? '?', 0, 2)) }}
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-small font-semibold">{{ $reg->student?->full_name }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">
                            {{ $reg->student?->nisn }} · {{ $reg->submitted_at?->translatedFormat('d M Y') ?? 'belum dikirim' }}
                        </p>
                    </div>
                    <x-status-badge :status="$reg->status" />
                </a>
            @empty
                <x-empty-state
                    icon="clipboard-check"
                    title="Antrean kosong"
                    description="Tidak ada pendaftaran yang menunggu verifikasi." />
            @endforelse
        </section>

        <div class="space-y-4">
            {{-- Incomplete data --}}
            <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
                <header class="border-b border-[var(--app-border)] px-4 py-3.5">
                    <h2 class="text-body font-semibold">Data belum lengkap</h2>
                </header>
                @forelse ($incomplete as $student)
                    <a href="{{ route('kesiswaan.students.show', $student->id) }}"
                       class="flex items-center gap-3 border-b border-[var(--app-border)] px-4 py-2.5 last:border-0 hover:bg-[var(--app-surface-alt)]">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-small font-medium">{{ $student->full_name }}</span>
                            <span class="block text-caption text-[var(--app-text-muted)]">{{ $student->registration?->completeness ?? 0 }}%</span>
                        </span>
                        <x-icon name="chevron-right" class="w-4 h-4 text-[var(--app-text-muted)]" />
                    </a>
                @empty
                    <p class="px-4 py-8 text-center text-small text-[var(--app-text-muted)]">Semua data siswa lengkap.</p>
                @endforelse
            </section>

            {{-- Recent activity --}}
            <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
                <header class="border-b border-[var(--app-border)] px-4 py-3.5">
                    <h2 class="text-body font-semibold">Aktivitas terakhir</h2>
                </header>
                @forelse ($activity as $log)
                    <div class="border-b border-[var(--app-border)] px-4 py-2.5 last:border-0">
                        <p class="text-small">{{ $log->description ?? $log->action }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $log->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-small text-[var(--app-text-muted)]">Belum ada aktivitas.</p>
                @endforelse
            </section>
        </div>
    </div>
</div>
@endsection
