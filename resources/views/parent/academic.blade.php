@extends('components.app-shell')

@section('title', 'Akademik '.$student->full_name)
@section('page-title', 'Akademik')
@section('page-description', $student->full_name)

@section('content')
<div class="space-y-5 max-w-3xl">

    @if (! $enrollment)
        <x-empty-state
            icon="award"
            title="Belum ada data akademik"
            description="Siswa belum memiliki penempatan kelas pada tahun ajaran ini." />
    @elseif ($grades->isEmpty())
        <x-empty-state
            icon="award"
            title="Belum ada nilai terbit"
            description="Nilai yang sudah diterbitkan akan tampil di sini. Nilai draf tidak ditampilkan kepada orang tua." />
    @else
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-stat-card label="Kelas" :value="$enrollment->classroom?->name ?? '—'" />
            <x-stat-card label="Mata Pelajaran" :value="$grades->pluck('subject.name')->unique()->count()" />
            <x-stat-card label="Rata-rata" :value="$average" tone="success" />
        </section>

        <section class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
            <header class="border-b border-[var(--app-border)] px-4 py-3.5 sm:px-5">
                <h2 class="text-body font-semibold">Nilai yang telah terbit</h2>
            </header>

            {{-- Desktop table --}}
            <div class="hidden sm:block">
                <table class="min-w-full divide-y divide-[var(--app-border)] text-small">
                    <thead class="bg-[var(--app-surface-alt)] text-left text-caption uppercase tracking-wide text-[var(--app-text-muted)]">
                        <tr>
                            <th class="px-4 py-2.5 font-semibold sm:px-5">Mata Pelajaran</th>
                            <th class="px-4 py-2.5 font-semibold">Semester</th>
                            <th class="px-4 py-2.5 font-semibold text-right">Nilai</th>
                            <th class="px-4 py-2.5 font-semibold text-right sm:px-5">Predikat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--app-border)]">
                        @foreach ($grades as $grade)
                            <tr>
                                <td class="px-4 py-3 font-medium sm:px-5">{{ $grade->subject?->name }}</td>
                                <td class="px-4 py-3 text-[var(--app-text-secondary)]">{{ $grade->term }}</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ (float) $grade->score }}</td>
                                <td class="px-4 py-3 text-right sm:px-5">
                                    <span class="rounded-full bg-[var(--app-surface-alt)] px-2 py-0.5 text-caption font-bold">{{ $grade->predicateLabel() }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile cards: a 4-column table at 360px is unreadable --}}
            <ul class="divide-y divide-[var(--app-border)] sm:hidden">
                @foreach ($grades as $grade)
                    <li class="px-4 py-3">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 flex-1 truncate text-small font-medium">{{ $grade->subject?->name }}</p>
                            <span class="shrink-0 text-body font-semibold tabular-nums">{{ (float) $grade->score }}</span>
                        </div>
                        <p class="mt-0.5 text-caption text-[var(--app-text-muted)]">
                            Semester {{ $grade->term }} · {{ $grade->predicateLabel() }}
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
