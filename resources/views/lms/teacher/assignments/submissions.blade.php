@extends('components.app-shell')

@section('title', 'Pengumpulan · '.$assignment->title)
@section('page-title', 'Pengumpulan Tugas')
@section('page-description', $assignment->title.' · '.$course->title)

@section('content')
<div class="space-y-4">
    @if (session('success')) <x-alert variant="success" :message="session('success')" /> @endif
    @forelse ($assignment->submissions as $submission)
        <x-card>
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="font-semibold">{{ $submission->student?->full_name ?? 'Siswa' }}</h2>
                    <p class="text-caption text-[var(--app-text-muted)]">{{ $submission->submitted_at?->format('d M Y H:i') }}{{ $submission->is_late ? ' · Terlambat' : '' }}</p>
                    @if ($submission->body)<p class="mt-3 whitespace-pre-line text-small">{{ $submission->body }}</p>@endif
                    @if ($submission->workspace_file_version_id)
                        <a class="btn btn-secondary mt-3" href="{{ route('lms.teacher.submissions.workspace-download', $submission) }}">Unduh file Drive yang dikumpulkan</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('lms.teacher.submissions.grade', [$course, $assignment, $submission]) }}" class="w-full max-w-sm space-y-3">
                    @csrf @method('PUT')
                    <x-form-field name="score" label="Nilai" type="number" min="0" max="{{ $assignment->max_score }}" step="0.01" :value="$submission->score" required />
                    <x-form-field name="feedback" label="Feedback" type="textarea" :value="$submission->feedback" />
                    <button class="btn btn-primary w-full justify-center">Simpan Nilai</button>
                </form>
            </div>
        </x-card>
    @empty
        <x-card><x-empty-state icon="inbox" title="Belum ada pengumpulan" message="Belum ada siswa yang mengirim tugas ini." /></x-card>
    @endforelse
</div>
@endsection
