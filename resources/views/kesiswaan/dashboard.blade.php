@extends('components.app-shell')

@section('title', 'Dashboard Kesiswaan')
@section('page-title', 'Dashboard')
@section('page-description', 'Kondisi data siswa sekolah')

@section('page-actions')
    <a href="{{ route('laporan.index') }}" class="btn btn-primary">
        <x-icon name="file-text" class="w-4 h-4" />
        Buat Laporan
    </a>
@endsection

@section('content')

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <x-stat-card label="Total Siswa" icon="users" tone="brand" :value="$students"
                 hint="Seluruh siswa terdaftar" />
    <x-stat-card label="Terverifikasi" icon="check-circle" tone="success" :value="$verified"
                 :hint="$students > 0 ? round($verified / $students * 100).'% dari total' : '—'" />
    <x-stat-card label="Menunggu Verifikasi" icon="clock" tone="warning" :value="$summary['pending']"
                 hint="Pendaftaran masuk" />
    <x-stat-card label="Perlu Perbaikan" icon="rotate-ccw" tone="danger" :value="$summary['revision']"
                 hint="Menunggu siswa" />
</div>

<div class="grid lg:grid-cols-2 gap-4 sm:gap-5 mt-4 sm:mt-5">

    {{-- ---------- Class distribution ---------- --}}
    <x-card title="Siswa per Kelas" icon="users"
            description="Jumlah siswa yang ditempatkan di setiap kelas">
        <x-slot:actions>
            <a href="{{ route('kesiswaan.statistics') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">
                Statistik lengkap
            </a>
        </x-slot:actions>

        @if (empty($byClass))
            <x-empty-state icon="users" compact title="Belum ada kelas"
                          description="Tambahkan kelas di Master Data terlebih dahulu." />
        @else
            @php $max = max(1, max(array_column($byClass, 'total'))); @endphp
            <ul class="space-y-3">
                @foreach ($byClass as $row)
                    <li>
                        <div class="flex justify-between gap-3 mb-1.5">
                            <span class="text-small text-[var(--app-text)] truncate">{{ $row['class'] }}</span>
                            <span class="text-small font-bold tabular-nums shrink-0">{{ $row['total'] }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-[var(--app-surface-muted)] overflow-hidden">
                            <div class="h-full rounded-full bg-brand-500" style="width: {{ $row['total'] / $max * 100 }}%"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- ---------- Gender ---------- --}}
    <x-card title="Komposisi Siswa" icon="chart-bar">
        @php
            $male = $byGender['L'] ?? 0;
            $female = $byGender['P'] ?? 0;
            $total = max(1, $male + $female);
        @endphp

        @if ($male + $female === 0)
            <x-empty-state icon="chart" compact title="Belum ada data"
                          description="Data akan muncul setelah siswa terdaftar." />
        @else
            <div class="flex items-center gap-5">
                <div class="relative w-28 h-28 shrink-0">
                    <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90">
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--color-ink-100)" stroke-width="4"/>
                        <circle cx="18" cy="18" r="15.9155" fill="none" stroke="var(--color-brand-500)" stroke-width="4"
                                stroke-dasharray="{{ $male / $total * 100 }}, 100" stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 grid place-items-center">
                        <span class="text-h3 font-bold tabular-nums">{{ $male + $female }}</span>
                    </div>
                </div>

                <ul class="flex-1 space-y-3">
                    <li class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-brand-500 shrink-0"></span>
                        <span class="text-small text-[var(--app-text-muted)] flex-1">Laki-laki</span>
                        <span class="text-small font-bold tabular-nums">{{ $male }}</span>
                        <span class="text-caption text-[var(--app-text-subtle)] w-10 text-right tabular-nums">{{ round($male / $total * 100) }}%</span>
                    </li>
                    <li class="flex items-center gap-2.5">
                        <span class="w-2.5 h-2.5 rounded-full bg-ink-200 shrink-0"></span>
                        <span class="text-small text-[var(--app-text-muted)] flex-1">Perempuan</span>
                        <span class="text-small font-bold tabular-nums">{{ $female }}</span>
                        <span class="text-caption text-[var(--app-text-subtle)] w-10 text-right tabular-nums">{{ round($female / $total * 100) }}%</span>
                    </li>
                </ul>
            </div>

            <dl class="mt-5 pt-4 border-t border-[var(--app-border)] space-y-2.5">
                @foreach ($byEntryYear as $year => $count)
                    <div class="flex justify-between gap-3 text-small">
                        <dt class="text-[var(--app-text-muted)]">Angkatan {{ $year }}</dt>
                        <dd class="font-semibold tabular-nums">{{ $count }} siswa</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </x-card>
</div>

{{-- ---------- Quick links ---------- --}}
<x-card class="mt-4 sm:mt-5" title="Akses Cepat" icon="sparkles">
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        @foreach ([
            ['Data Siswa', 'Cari dan filter seluruh data siswa', 'users', 'kesiswaan.students', 'primary'],
            ['Statistik', 'Analisis sebaran siswa', 'chart', 'kesiswaan.statistics', 'secondary'],
            ['Rekapitulasi', 'Ringkasan per kelas dan status', 'list-checks', 'kesiswaan.rekap', 'secondary'],
            ['Laporan', 'Ekspor Excel, CSV, atau PDF', 'file-text', 'laporan.index', 'secondary'],
        ] as [$title, $desc, $icon, $route, $variant])
            <a href="{{ route($route) }}" class="group flex items-start gap-3 p-4 rounded-[var(--radius-md)] border border-[var(--app-border)] hover:border-brand-300 hover:bg-[var(--app-primary-soft)] transition-colors">
                <span class="shrink-0 grid place-items-center w-9 h-9 rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] group-hover:bg-white text-[var(--app-text-muted)] group-hover:text-[var(--app-primary)] transition-colors">
                    <x-icon :name="$icon" class="w-[18px] h-[18px]" />
                </span>
                <div class="min-w-0">
                    <p class="text-small font-semibold text-[var(--app-text)]">{{ $title }}</p>
                    <p class="text-caption text-[var(--app-text-muted)] mt-0.5">{{ $desc }}</p>
                </div>
            </a>
        @endforeach
    </div>
</x-card>
@endsection
