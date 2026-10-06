@extends('components.app-shell')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')
@section('page-description', 'Antrean verifikasi dan kondisi pendaftaran siswa')

@section('page-actions')
    <form method="GET" class="flex flex-wrap items-center gap-2">
        <label for="range" class="sr-only">Rentang dashboard</label>
        <select id="range" name="range" class="field !min-h-10 !py-1.5 !text-small w-auto">
            @foreach (['today' => 'Today', '7d' => '7 Days', '30d' => '30 Days', 'month' => 'This Month', 'semester' => 'This Semester', 'year' => 'This Academic Year'] as $key => $label)
                <option value="{{ $key }}" @selected($range === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="attendance_threshold" class="sr-only">Batas attendance</label>
        <select id="attendance_threshold" name="attendance_threshold" class="field !min-h-10 !py-1.5 !text-small w-auto" title="Batas siswa yang perlu perhatian">
            @foreach ([70, 75, 80, 85] as $threshold)
                <option value="{{ $threshold }}" @selected((int) $attendanceThreshold === $threshold)>&lt; {{ $threshold }}% attendance</option>
            @endforeach
        </select>
        @if ($years->isNotEmpty())
            <label for="academic_year_id" class="sr-only">Tahun ajaran</label>
            <select id="academic_year_id" name="academic_year_id" class="field !min-h-10 !py-1.5 !text-small w-auto">
                <option value="">Semua tahun ajaran</option>
                @foreach ($years as $id => $name)
                    <option value="{{ $id }}" @selected((string) $yearId === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        @endif
        <button class="btn btn-secondary btn-sm" type="submit"><x-icon name="filter" class="w-4 h-4" /> Terapkan</button>
    </form>
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

{{-- ============ School Pulse ============ --}}
<div class="mt-4 sm:mt-5 space-y-4 sm:space-y-5">
    <x-card title="School Pulse" icon="activity"
            description="Ringkasan kondisi sekolah untuk keputusan kurang dari 30 detik.">
        <div class="grid md:grid-cols-3 gap-3">
            <div class="rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] p-4">
                <p class="text-caption text-[var(--app-text-muted)]">Attendance</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $analytics['attendance']['rate'] !== null ? $analytics['attendance']['rate'].'%' : '—' }}</p>
                <p class="mt-1 text-caption text-[var(--app-text-subtle)]">Target {{ $analytics['attendance']['target'] }}%</p>
            </div>
            <div class="rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] p-4">
                <p class="text-caption text-[var(--app-text-muted)]">Average Academic Score</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $analytics['academic']['average'] !== null ? $analytics['academic']['average'] : '—' }}</p>
                <p class="mt-1 text-caption text-[var(--app-text-subtle)]">Target {{ $analytics['academic']['target'] }}</p>
            </div>
            <div class="rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] p-4">
                <p class="text-caption text-[var(--app-text-muted)]">PPDB Conversion</p>
                <p class="mt-1 text-2xl font-bold tabular-nums">{{ $analytics['admission']['conversion'] !== null ? $analytics['admission']['conversion'].'%' : '—' }}</p>
                <p class="mt-1 text-caption text-[var(--app-text-subtle)]">Applicants → Verified</p>
            </div>
        </div>
    </x-card>

    {{-- ============ Needs attention ============ --}}
    <x-card title="Needs Attention" icon="alert-triangle"
            description="Indikator berbasis data yang memerlukan tindakan berikutnya.">
        <p class="mb-3 text-caption font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">Student Attention Signals</p>
        @if ($analytics['insights'] === [])
            <x-empty-state icon="check-circle" compact title="Tidak ada sinyal prioritas"
                          description="Belum ada indikator yang melewati threshold saat ini." />
        @else
            <div class="grid md:grid-cols-3 gap-3">
                @foreach ($analytics['insights'] as $insight)
                    <div class="rounded-[var(--radius-md)] border border-[var(--app-border)] p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid place-items-center w-9 h-9 rounded-full bg-[var(--app-warning-soft)] text-[var(--app-warning)]">
                                <x-icon :name="$insight['icon']" class="w-4 h-4" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-small font-semibold text-[var(--app-text)]">{{ $insight['title'] }}</p>
                                <p class="mt-1 text-caption text-[var(--app-text-muted)]">{{ $insight['detail'] }}</p>
                                <a href="{{ $insight['href'] }}" class="mt-3 inline-flex items-center gap-1 text-caption font-semibold text-[var(--app-primary)] hover:underline">
                                    {{ $insight['action'] }} <x-icon name="arrow-up-right" class="w-3.5 h-3.5" />
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="grid xl:grid-cols-2 gap-4 sm:gap-5">
        {{-- ============ Attendance analytics ============ --}}
        <x-card title="Attendance Analytics" icon="calendar-check"
                description="Attendance rate, trend, status, dan siswa di bawah threshold.">
            @if (! $analytics['attendance']['available'])
                <x-empty-state icon="calendar-off" compact title="Data attendance belum tersedia"
                              description="Belum ada tabel attendance yang dapat dihitung." />
            @else
                <div class="flex items-end justify-between gap-4">
                    <div>
                        <p class="text-3xl font-bold tabular-nums">{{ $analytics['attendance']['rate'] !== null ? $analytics['attendance']['rate'].'%' : '—' }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">Attendance pada rentang {{ $range }}</p>
                    </div>
                    <span class="rounded-full bg-[var(--app-success-soft)] px-2.5 py-1 text-caption font-semibold text-[var(--app-success)]">Target {{ $analytics['attendance']['target'] }}%</span>
                </div>
                <div class="mt-4 h-20 flex items-end gap-1" aria-label="Attendance trend">
                    @forelse ($analytics['attendance']['trend'] as $point)
                        <div class="flex-1 rounded-t bg-[var(--app-primary)]/70" style="height: {{ max(6, (float) ($point['rate'] ?? 0)) }}%" title="{{ $point['day'] }}: {{ $point['rate'] ?? '—' }}%"></div>
                    @empty
                        <div class="w-full text-center text-caption text-[var(--app-text-subtle)]">Belum cukup data untuk membuat tren.</div>
                    @endforelse
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-caption">
                    @foreach ([['label' => 'Hadir / terlambat', 'keys' => ['present', 'late']], ['label' => 'Sakit', 'keys' => ['sick']], ['label' => 'Izin', 'keys' => ['excused']], ['label' => 'Alpa', 'keys' => ['absent']] ] as $item)
                        <div class="flex justify-between rounded bg-[var(--app-surface-muted)] px-3 py-2"><span>{{ $item['label'] }}</span><strong>{{ collect($item['keys'])->sum(fn ($key) => $analytics['attendance']['statuses'][$key] ?? 0) }}</strong></div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center justify-between border-t border-[var(--app-border)] pt-3">
                    <span class="text-small font-semibold">{{ $analytics['attendance']['low_count'] }} siswa &lt; {{ $attendanceThreshold }}%</span>
                    <a href="{{ route('kesiswaan.students') }}" class="btn btn-secondary btn-sm">Review Students</a>
                </div>
            @endif
        </x-card>

        {{-- ============ Academic analytics ============ --}}
        <x-card title="Academic Performance" icon="graduation-cap"
                description="Nilai terbit, distribusi, subject performance, dan kelas.">
            @if (! $analytics['academic']['available'] || $analytics['academic']['average'] === null)
                <x-empty-state icon="bar-chart-3" compact title="Belum cukup data akademik"
                              description="Belum ada nilai terbit yang dapat dianalisis." />
            @else
                <div class="flex items-end justify-between">
                    <div><p class="text-3xl font-bold tabular-nums">{{ $analytics['academic']['average'] }}</p><p class="text-caption text-[var(--app-text-muted)]">Average Academic Score</p></div>
                    <div class="text-right"><p class="text-xl font-bold tabular-nums">{{ $analytics['academic']['median'] ?? '—' }}</p><p class="text-caption text-[var(--app-text-muted)]">Median</p></div>
                    <span class="text-caption font-semibold {{ $analytics['academic']['average'] >= $analytics['academic']['target'] ? 'text-[var(--app-success)]' : 'text-[var(--app-danger)]' }}">Target {{ $analytics['academic']['target'] }}</span>
                </div>
                <div class="mt-4 space-y-2">
                    @foreach (['90–100', '80–89', '70–79', '<70'] as $bucket)
                        @php
                            $value = $analytics['academic']['distribution'][$bucket] ?? 0;
                            $distributionTotal = max(1, array_sum($analytics['academic']['distribution']));
                        @endphp
                        <div class="flex items-center gap-3 text-caption">
                            <span class="w-12 shrink-0">{{ $bucket }}</span>
                            <div class="h-2 flex-1 rounded-full bg-[var(--app-surface-muted)]">
                                <div class="h-full rounded-full bg-[var(--app-primary)]" style="width: {{ $value > 0 ? min(100, $value / $distributionTotal * 100) : 0 }}%"></div>
                            </div>
                            <strong class="w-8 text-right">{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 grid sm:grid-cols-2 gap-3">
                    <div>
                        <p class="text-caption font-semibold text-[var(--app-text-muted)]">Top Subjects</p>
                        @forelse (array_slice($analytics['academic']['subjects'], 0, 3) as $subject)
                            <p class="mt-2 flex justify-between text-small"><span>{{ $subject['name'] }}</span><strong>{{ $subject['average'] }}</strong></p>
                        @empty
                            <p class="mt-2 text-caption text-[var(--app-text-subtle)]">Belum tersedia.</p>
                        @endforelse
                    </div>
                    <div>
                        <p class="text-caption font-semibold text-[var(--app-text-muted)]">Class Performance</p>
                        @forelse (array_slice($analytics['academic']['classes'], 0, 3) as $class)
                            <p class="mt-2 flex justify-between text-small"><span>{{ $class['name'] }}</span><strong>{{ $class['average'] }}</strong></p>
                        @empty
                            <p class="mt-2 text-caption text-[var(--app-text-subtle)]">Belum tersedia.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </x-card>
    </div>

    <div class="grid xl:grid-cols-3 gap-4 sm:gap-5">
        {{-- ============ Student breakdown ============ --}}
        <x-card title="Students by Grade" icon="users">
            @forelse ($analytics['breakdown']['by_grade'] as $grade)
                <div class="flex items-center justify-between py-2 border-b border-[var(--app-border)] last:border-0 text-small"><span>{{ $grade['label'] }}</span><strong>{{ $grade['value'] }}</strong></div>
            @empty
                <p class="text-caption text-[var(--app-text-subtle)]">Belum cukup data untuk breakdown kelas.</p>
            @endforelse
            <div class="mt-4 border-t border-[var(--app-border)] pt-4">
                <div class="flex items-center justify-between">
                    <p class="text-caption font-semibold text-[var(--app-text-muted)]">Student Growth</p>
                    <span class="text-caption font-semibold {{ ($analytics['breakdown']['growth']['change'] ?? 0) >= 0 ? 'text-[var(--app-success)]' : 'text-[var(--app-danger)]' }}">
                        @if ($analytics['breakdown']['growth']['change'] !== null)
                            {{ $analytics['breakdown']['growth']['change'] >= 0 ? '+' : '' }}{{ $analytics['breakdown']['growth']['change'] }} ({{ $analytics['breakdown']['growth']['change_pct'] }}%)
                        @else
                            Belum cukup data
                        @endif
                    </span>
                </div>
                <div class="mt-2 flex items-end gap-1 h-12">
                    @forelse (array_slice($analytics['breakdown']['growth']['points'], -6) as $point)
                        <div class="flex-1 rounded-t bg-[var(--app-primary)]/70" style="height: {{ max(6, min(100, $point['value'])) }}%" title="{{ $point['label'] }}: {{ $point['value'] }}"></div>
                    @empty
                        <span class="text-caption text-[var(--app-text-subtle)]">Belum cukup data untuk membuat tren.</span>
                    @endforelse
                </div>
            </div>
        </x-card>

        {{-- ============ PPDB funnel ============ --}}
        <x-card title="PPDB Funnel" icon="filter">
            @foreach ($analytics['admission']['stages'] as $stage)
                <div class="flex items-center justify-between py-2 border-b border-[var(--app-border)] last:border-0 text-small"><span>{{ $stage['label'] }}</span><strong>{{ number_format($stage['value'], 0, ',', '.') }}</strong></div>
            @endforeach
            <p class="mt-3 text-caption text-[var(--app-text-muted)]">Conversion Applicant → Verified: <strong>{{ $analytics['admission']['conversion'] !== null ? $analytics['admission']['conversion'].'%' : '—' }}</strong></p>
        </x-card>

        {{-- ============ LMS analytics ============ --}}
        <x-card title="LMS Analytics" icon="book-open">
            @if (! $analytics['lms']['available'])
                <p class="text-caption text-[var(--app-text-subtle)]">Data belum tersedia untuk modul LMS.</p>
            @else
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded bg-[var(--app-surface-muted)] p-3"><strong class="block text-xl">{{ $analytics['lms']['active_courses'] }}</strong><span class="text-caption">Courses</span></div>
                    <div class="rounded bg-[var(--app-surface-muted)] p-3"><strong class="block text-xl">{{ $analytics['lms']['enrollments'] ?? '—' }}</strong><span class="text-caption">Enrollments</span></div>
                    <div class="rounded bg-[var(--app-surface-muted)] p-3"><strong class="block text-xl">{{ $analytics['lms']['published_lessons'] ?? '—' }}</strong><span class="text-caption">Lessons</span></div>
                </div>
                <p class="mt-3 text-caption text-[var(--app-text-subtle)]">Course completion belum dihitung karena tabel progress belum tersedia.</p>
                <p class="mt-2 text-caption text-[var(--app-text-subtle)]">Data belum tersedia: finance, employee attendance, assignment/quiz, dan CMS traffic.</p>
            @endif
        </x-card>
    </div>
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