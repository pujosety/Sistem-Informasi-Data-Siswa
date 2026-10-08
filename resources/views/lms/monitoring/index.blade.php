@extends('components.app-shell')

@section('title', 'LMS · Monitoring')
@section('page-title', 'Monitoring LMS')
@section('page-description', 'Ringkasan aktivitas pembelajaran dan pekerjaan yang perlu ditindaklanjuti.')

@section('content')
<div class="space-y-5">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['label' => 'Course aktif', 'value' => $courseCount],
            ['label' => 'Peserta aktif', 'value' => $activeEnrollmentCount],
            ['label' => 'Lesson terbit', 'value' => $publishedLessonCount],
            ['label' => 'Tugas perlu dinilai', 'value' => $pendingAssignmentCount],
            ['label' => 'Quiz perlu diperiksa', 'value' => $pendingQuizCount],
        ] as $metric)
            <x-card>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $metric['label'] }}</p>
                <p class="mt-2 text-h2 font-semibold">{{ number_format($metric['value']) }}</p>
            </x-card>
        @endforeach
    </div>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Course terbaru</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Pantau roster dan materi yang tersedia pada setiap course.</p>
            </div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-left text-small">
                <thead><tr class="border-b border-[var(--app-border)] text-caption text-[var(--app-text-muted)]"><th class="px-3 py-3">Course</th><th class="px-3 py-3">Guru</th><th class="px-3 py-3">Peserta</th><th class="px-3 py-3">Lesson terbit</th><th class="px-3 py-3">Status</th></tr></thead>
                <tbody class="divide-y divide-[var(--app-border)]">
                    @forelse ($courses as $course)
                        <tr>
                            <td class="px-3 py-3"><p class="font-medium">{{ $course->title }}</p><p class="text-caption text-[var(--app-text-muted)]">{{ $course->subject?->name }} · {{ $course->classroom?->name }}</p></td>
                            <td class="px-3 py-3">{{ $course->teacher?->name ?? $course->teacher?->email ?? '—' }}</td>
                            <td class="px-3 py-3">{{ $course->active_enrollments_count }}</td>
                            <td class="px-3 py-3">{{ $course->published_lessons_count }}</td>
                            <td class="px-3 py-3"><span class="badge {{ $course->status === 'published' ? 'badge-success' : 'badge-neutral' }}">{{ $course->status === 'published' ? 'Terbit' : 'Draft' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-[var(--app-text-muted)]">Belum ada course.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($courses->hasPages())
            <div class="mt-4">{{ $courses->links() }}</div>
        @endif
    </x-card>
</div>
@endsection
