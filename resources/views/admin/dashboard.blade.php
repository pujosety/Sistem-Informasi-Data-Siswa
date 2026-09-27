@extends('components.app-shell')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')
@section('page-description', 'Antrean verifikasi dan kondisi pendaftaran siswa')

@section('page-actions')
    @if ($years->isNotEmpty())
        <form method="GET" class="flex items-center gap-2">
            <label for="academic_year_id" class="sr-only">Tahun ajaran</label>
            <select id="academic_year_id" name="academic_year_id" class="field !min-h-10 !py-1.5 !text-small w-auto"
                    onchange="this.form.submit()">
                <option value="">Semua tahun ajaran</option>
                @foreach ($years as $id => $name)
                    <option value="{{ $id }}" @selected((string) $yearId === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </form>
    @endif
@endsection

@section('content')

{{-- ============ Actionable counters ============ --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    <x-stat-card label="Perlu Tindakan Anda" icon="alert-circle" tone="brand"
                 :value="$summary['pending']"
                 hint="Menunggu verifikasi"
                 :href="route('admin.registrations', ['status' => 'pending'])" />

    <x-stat-card label="Perlu Diperbaiki" icon="rotate-ccw" tone="warning"
                 :value="$summary['revision']"
                 hint="Menunggu siswa"
                 :href="route('admin.registrations', ['status' => 'revision'])" />

    <x-stat-card label="Terverifikasi" icon="check-circle" tone="success"
                 :value="$summary['verified']"
                 :href="route('admin.registrations', ['status' => 'verified'])" />

    <x-stat-card label="Total Pendaftar" icon="users" tone="neutral"
                 :value="$summary['total']"
                 hint="Tahun ajaran aktif" />
</div>

<div class="grid lg:grid-cols-3 gap-4 sm:gap-5 mt-4 sm:mt-5">

    {{-- ============ Verification queue ============ --}}
    <div class="lg:col-span-2 space-y-4 sm:space-y-5">
        <x-card title="Antrean Verifikasi" icon="clipboard-check"
                description="Pendaftaran yang menunggu pemeriksaan Anda">
            <x-slot:actions>
                <a href="{{ route('admin.registrations') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">
                    Lihat semua
                </a>
            </x-slot:actions>

            @if ($pendingQueue->isEmpty())
                <x-empty-state icon="check-circle" compact
                              title="Tidak ada pendaftaran yang perlu diverifikasi"
                              description="Semua pendaftaran terbaru sudah diperiksa.">
                    <x-slot:action>
                        <a href="{{ route('admin.registrations') }}" class="btn btn-secondary btn-sm">
                            Lihat semua pendaftar
                        </a>
                    </x-slot:action>
                </x-empty-state>
            @else
                <ul class="divide-y divide-[var(--app-border)] -mx-4 sm:-mx-5">
                    @foreach ($pendingQueue as $student)
                        <li>
                            <a href="{{ route('admin.registrations.show', $student) }}"
                               class="flex items-center gap-3.5 px-4 sm:px-5 py-3.5 hover:bg-[var(--app-surface-muted)] transition-colors">
                                <span class="shrink-0 grid place-items-center w-10 h-10 rounded-full bg-brand-50 text-brand-700 text-small font-bold">
                                    {{ strtoupper(mb_substr($student->full_name, 0, 2)) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-body font-semibold text-[var(--app-text)] truncate">{{ $student->full_name }}</p>
                                    <p class="text-caption text-[var(--app-text-muted)]">
                                        {{ $student->nisn }} ·
                                        {{ $student->registration?->submitted_at?->diffForHumans() ?? 'baru dikirim' }}
                                    </p>
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="text-caption text-[var(--app-text-muted)]">Kelengkapan</p>
                                    <p class="text-small font-bold tabular-nums">{{ $student->registration?->completeness ?? 0 }}%</p>
                                </div>
                                <x-icon name="chevron-right" class="w-4 h-4 text-[var(--app-text-subtle)] shrink-0 hidden sm:block" />
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- ============ Trend ============ --}}
        <x-card title="Pendaftaran 30 Hari Terakhir" icon="chart"
                description="Jumlah pendaftaran per hari">
            @php
                $max = max(1, max($daily ?: [1]));
                $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());
            @endphp

            @if ($daily === [])
                <x-empty-state icon="chart" compact title="Belum ada data"
                              description="Grafik muncul setelah ada pendaftaran masuk." />
            @else
                <div class="flex items-end gap-[3px] h-40" role="img"
                     aria-label="Grafik pendaftaran 30 hari terakhir, total {{ array_sum($daily) }} pendaftaran">
                    @foreach ($days as $day)
                        @php $count = $daily[$day] ?? 0; @endphp
                        <div class="flex-1 group relative flex flex-col justify-end h-full">
                            <div @class([
                                'w-full rounded-t transition-colors',
                                'bg-brand-500' => $count > 0,
                                'bg-[var(--app-surface-muted)]' => $count === 0,
                            ]) style="height: {{ $count > 0 ? max(6, $count / $max * 100) : 4 }}%"
                                 title="{{ \Carbon\Carbon::parse($day)->translatedFormat('d M Y') }}: {{ $count }}"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-caption text-[var(--app-text-subtle)] mt-2.5">
                    <span>{{ now()->subDays(29)->translatedFormat('d M') }}</span>
                    <span>{{ now()->translatedFormat('d M Y') }}</span>
                </div>
            @endif
        </x-card>
    </div>

    {{-- ============ Right rail ============ --}}
    <div class="space-y-4 sm:space-y-5">
        <x-card title="Status Pendaftaran" icon="chart-bar">
            <ul class="space-y-2.5">
                @forelse ($byStatus as $status => $count)
                    <li class="flex items-center justify-between gap-3">
                        <x-status-badge :status="$status" />
                        <span class="text-small font-bold tabular-nums">{{ $count }}</span>
                    </li>
                @empty
                    <li class="text-small text-[var(--app-text-subtle)]">Belum ada pendaftaran.</li>
                @endforelse
            </ul>
        </x-card>

        <x-card title="Pendaftar Terbaru" icon="clock">
            @if ($recent->isEmpty())
                <x-empty-state icon="inbox" compact title="Belum ada pendaftar" />
            @else
                <ul class="space-y-3">
                    @foreach ($recent as $student)
                        <li class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-1 w-1.5 h-1.5 rounded-full bg-brand-400"></span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.registrations.show', $student) }}"
                                   class="text-small font-medium text-[var(--app-text)] hover:underline block truncate">
                                    {{ $student->full_name }}
                                </a>
                                <p class="text-caption text-[var(--app-text-muted)]">
                                    {{ $student->registration?->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</div>
@endsection
