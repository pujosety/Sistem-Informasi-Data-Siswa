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

@php
    // Donut items are built here rather than in the component so the labels are
    // the ones the school uses. 'L'/'P' are the stored codes; a dashboard that
    // says "L" and "P" is showing a database value, not a fact.
    $genderItems = collect($byGender)
        ->map(fn ($total, $code) => [
            'label' => $code === 'L' ? 'Laki-laki' : ($code === 'P' ? 'Perempuan' : $code),
            'value' => (int) $total,
        ])
        ->values()
        ->all();

    $maxDaily = max(1, max($daily ?: [1]));
    $trendDays = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

    $timelineItems = $activity->map(fn ($log) => [
        'title' => $log->description ?: $log->action,
        'timestamp' => $log->created_at?->diffForHumans(),
        'description' => $log->user?->name,
        'tone' => str_contains($log->action, 'delete') || str_contains($log->action, 'reject') ? 'danger' : 'neutral',
    ])->all();

    // The right rail is reorderable, which means each card has to exist as its
    // own renderable string BEFORE the grid emits it. They are built here
    // rather than in an @if(false) block below, so there is exactly one copy of
    // each card in the file — a duplicated card that drifts from its twin is
    // the normal way this pattern goes wrong.
    $statusSlot = Blade::render(<<<'BLADE'
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
    BLADE, ['byStatus' => $byStatus]);

    $genderSlot = count($genderItems) > 0
        ? Blade::render(<<<'BLADE'
            <x-chart-card title="Distribusi Jenis Kelamin"
                          description="Seluruh siswa terdaftar"
                          height="h-auto">
                <x-slot:summary>
                    @foreach ($genderItems as $item)
                        {{ $item['label'] }}: {{ $item['value'] }} siswa.
                    @endforeach
                </x-slot:summary>

                <x-donut-chart :items="$genderItems" :size="180" :stroke="24" center-label="Siswa" />
            </x-chart-card>
        BLADE, ['genderItems' => $genderItems])
        : null;

    $activitySlot = $timelineItems !== []
        ? Blade::render(<<<'BLADE'
            <x-card title="Aktivitas Terakhir" icon="activity">
                <x-slot:actions>
                    <a href="{{ route('admin.activity') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">
                        Semua
                    </a>
                </x-slot:actions>

                <x-timeline :items="$timelineItems" />
            </x-card>
        BLADE, ['timelineItems' => $timelineItems])
        : null;

    $recentSlot = Blade::render(<<<'BLADE'
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
    BLADE, ['recent' => $recent]);
@endphp

{{-- ============ Actionable counters ============ --}}
{{-- Kept OUTSIDE the widget grid on purpose. These four answer "what needs me
     right now", and a user who reorders their dashboard must not be able to
     push the pending-verification count below the fold. --}}
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

{{-- ============ School at a glance ============ --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mt-4 sm:mt-5">
    <x-stat-card label="Siswa Aktif" icon="graduation-cap" tone="brand"
                 :value="$counts['students']"
                 :hint="$yearId ? 'Tahun ajaran terpilih' : 'Seluruh tahun ajaran'" />

    <x-stat-card label="Tenaga Pendidikan" icon="user-check" tone="info"
                 :value="$counts['teachers']" />

    <x-stat-card label="Kelas Aktif" icon="layers" tone="neutral"
                 :value="$counts['classes']"
                 :hint="$yearId ? 'Tahun ajaran terpilih' : 'Seluruh tahun ajaran'" />
</div>

{{-- ============ Body ============ --}}
{{-- The right rail is a WidgetGrid so its four cards can be reordered, hidden
     and restored per user; the counters above stay outside it, because a count
     the user can push below the fold stops being a count. --}}
<div class="grid lg:grid-cols-3 gap-4 sm:gap-5">

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
        <x-chart-card title="Pendaftaran 30 Hari Terakhir"
                      description="Jumlah pendaftaran per hari"
                      height="h-40">
            <x-slot:summary>
                Total {{ number_format(array_sum($daily ?: []), 0, ',', '.') }} pendaftaran dalam 30 hari terakhir,
                puncak {{ $maxDaily }} pada satu hari.
            </x-slot:summary>

            @if ($daily === [])
                <x-empty-state icon="chart" compact title="Belum ada data"
                              description="Grafik muncul setelah ada pendaftaran masuk." />
            @else
                <div class="flex items-end gap-[3px] h-full" role="img"
                     aria-label="Grafik pendaftaran 30 hari terakhir, total {{ array_sum($daily) }} pendaftaran">
                    @foreach ($trendDays as $day)
                        @php $count = $daily[$day] ?? 0; @endphp
                        <div class="flex-1 group relative flex flex-col justify-end h-full">
                            <div @class([
                                'w-full rounded-t transition-colors',
                                'bg-[var(--app-primary)]' => $count > 0,
                                'bg-[var(--app-surface-muted)]' => $count === 0,
                            ]) style="height: {{ $count > 0 ? max(6, $count / $maxDaily * 100) : 4 }}%"
                                 title="{{ \Carbon\Carbon::parse($day)->translatedFormat('d M Y') }}: {{ $count }}"></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-caption text-[var(--app-text-subtle)] mt-2.5">
                    <span>{{ now()->subDays(29)->translatedFormat('d M') }}</span>
                    <span>{{ now()->translatedFormat('d M Y') }}</span>
                </div>
            @endif
        </x-chart-card>
    </div>

    {{-- ============ Right rail (reorderable) ============ --}}
    <x-widget-grid class="space-y-4 sm:space-y-5" storage-key="sida.dashboard.admin-rail" :columns="1" :widgets="[
        ['id' => 'status',  'slot' => $statusSlot],
        ['id' => 'gender',  'slot' => $genderSlot],
        ['id' => 'activity','slot' => $activitySlot],
        ['id' => 'recent',  'slot' => $recentSlot],
    ]" />

@endsection