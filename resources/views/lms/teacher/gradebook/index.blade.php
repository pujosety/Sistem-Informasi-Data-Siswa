@extends('components.app-shell')

@section('title', 'LMS · Gradebook · '.$course->title)
@section('page-title', 'Gradebook')
@section('page-description', $course->title.' · ringkasan nilai peserta')

@section('content')
<div class="space-y-5">
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                <h2 class="mt-1 text-h2 font-semibold">{{ $course->title }}</h2>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">Nilai terbaru dari tugas dan kuis yang sudah diterbitkan.</p>
            </div>
            <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary btn-sm">Kembali ke course</a>
        </div>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-small">
                <thead>
                    <tr class="border-b border-[var(--app-border)] text-caption text-[var(--app-text-muted)]">
                        <th class="sticky left-0 min-w-48 bg-[var(--app-surface)] px-3 py-3">Siswa</th>
                        @foreach ($assignments as $assignment)
                            <th class="min-w-32 px-3 py-3">Tugas: {{ $assignment->title }}</th>
                        @endforeach
                        @foreach ($quizzes as $quiz)
                            <th class="min-w-32 px-3 py-3">Kuis: {{ $quiz->title }}</th>
                        @endforeach
                        <th class="min-w-24 px-3 py-3 text-right">Rata-rata</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--app-border)]">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="sticky left-0 bg-[var(--app-surface)] px-3 py-3 font-medium">{{ $row['student']?->full_name }}</td>
                            @foreach ($assignments as $assignment)
                                @php($score = $row['assignment_scores']->get($assignment->id))
                                <td class="px-3 py-3">{{ $score === null ? '—' : number_format((float) $score, 2) }}</td>
                            @endforeach
                            @foreach ($quizzes as $quiz)
                                @php($score = $row['quiz_scores']->get($quiz->id))
                                <td class="px-3 py-3">{{ $score === null ? '—' : number_format((float) $score, 2) }}</td>
                            @endforeach
                            <td class="px-3 py-3 text-right font-semibold text-[var(--app-primary)]">
                                {{ $row['average'] === null ? '—' : number_format((float) $row['average'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 2 + $assignments->count() + $quizzes->count() }}" class="px-3 py-8 text-center text-[var(--app-text-muted)]">Belum ada peserta aktif di course ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assignments->isEmpty() && $quizzes->isEmpty())
            <p class="mt-4 text-caption text-[var(--app-text-muted)]">Belum ada tugas atau kuis terbit. Terbitkan evaluasi agar nilainya muncul di sini.</p>
        @endif
    </x-card>
</div>
@endsection
