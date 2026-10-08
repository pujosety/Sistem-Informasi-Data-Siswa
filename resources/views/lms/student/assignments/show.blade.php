@extends('components.app-shell')

@section('title', 'Tugas · '.$assignment->title)
@section('page-title', $assignment->title)
@section('page-description', $course->title)

@section('content')
<div class="max-w-3xl space-y-5">
    @if (session('success'))
        <x-alert variant="success" :message="session('success')" />
    @endif
    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->subject?->name }}</p>
                <h2 class="mt-1 text-h2 font-semibold">{{ $assignment->title }}</h2>
            </div>
            @if ($submission)
                <span class="badge {{ $submission->status === 'graded' ? 'badge-success' : 'badge-neutral' }}">
                    {{ $submission->status === 'graded' ? 'Sudah dinilai' : 'Terkirim' }}
                </span>
            @endif
        </div>
        <div class="mt-4 whitespace-pre-line text-body text-[var(--app-text-muted)]">{{ $assignment->description ?: 'Tidak ada instruksi tambahan.' }}</div>
        <dl class="mt-5 grid gap-3 sm:grid-cols-3 text-small">
            <div><dt class="text-[var(--app-text-muted)]">Batas waktu</dt><dd class="font-semibold">{{ $assignment->due_at?->format('d M Y H:i') ?? 'Tidak ditentukan' }}</dd></div>
            <div><dt class="text-[var(--app-text-muted)]">Nilai maksimal</dt><dd class="font-semibold">{{ $assignment->max_score }}</dd></div>
            <div><dt class="text-[var(--app-text-muted)]">Percobaan</dt><dd class="font-semibold">{{ $assignment->max_attempts }}</dd></div>
        </dl>
    </x-card>

    @if ($submission)
        <x-card title="Pengumpulan Terakhir" icon="check-circle">
            <p class="text-small text-[var(--app-text-muted)]">Dikirim {{ $submission->submitted_at?->format('d M Y H:i') }}{{ $submission->is_late ? ' · Terlambat' : '' }}</p>
            @if ($submission->body)<p class="mt-3 whitespace-pre-line text-body">{{ $submission->body }}</p>@endif
            @if ($submission->status === 'graded')
                <div class="mt-4 rounded-[var(--radius-md)] bg-[var(--app-success-soft)] p-4">
                    <p class="text-small font-semibold text-[var(--app-success)]">Nilai: {{ $submission->score }} / {{ $assignment->max_score }}</p>
                    <p class="mt-1 whitespace-pre-line text-small text-[var(--app-text)]">{{ $submission->feedback ?: 'Tidak ada feedback tambahan.' }}</p>
                </div>
            @endif
        </x-card>
    @else
        <form method="POST" action="{{ route('lms.student.assignments.submit', [$course, $assignment]) }}" enctype="multipart/form-data">
            @csrf
            <x-card title="Kirim Jawaban" icon="send">
                @if (in_array($assignment->submission_type, ['text', 'text_file'], true))
                    <x-form-field name="body" label="Jawaban" type="textarea" required="{{ $assignment->submission_type === 'text' ? 'true' : 'false' }}" />
                @endif
                @if (in_array($assignment->submission_type, ['file', 'text_file'], true))
                    <x-form-field name="file" label="File jawaban" type="file" class="mt-4" hint="PDF, Office, gambar; maksimal 10 MB." />
                @endif
                <div class="mt-5 flex justify-end"><button class="btn btn-primary">Kirim Tugas</button></div>
            </x-card>
        </form>
    @endif
</div>
@endsection
