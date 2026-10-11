@extends('components.app-shell')

@section('title', 'Kuis · '.$quiz->title)
@section('page-title', $quiz->title)
@section('page-description', $course->title)

@section('content')
<div class="mx-auto max-w-3xl space-y-4">
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->title }}</p>
                <h2 class="mt-1 text-h2 font-semibold text-[var(--app-text)]">{{ $quiz->title }}</h2>
                @if ($quiz->description)
                    <p class="mt-2 text-body text-[var(--app-text-muted)]">{{ $quiz->description }}</p>
                @endif
            </div>
            <div class="text-right text-caption text-[var(--app-text-muted)]">
                <p>{{ $quiz->questions->count() }} pertanyaan</p>
                <p>{{ $quiz->attempt_limit }} percobaan</p>
            </div>
        </div>
    </x-card>

    @if ($latestAttempt?->status === 'submitted' && $quiz->show_results)
        <x-card>
            <p class="text-caption text-[var(--app-text-muted)]">Percobaan terakhir</p>
            <p class="mt-1 text-h2 font-semibold">{{ number_format((float) $latestAttempt->score, 2) }}%</p>
            <p class="mt-1 text-caption text-[var(--app-text-muted)]">Dikumpulkan {{ $latestAttempt->submitted_at?->format('d M Y H:i') }}</p>
        </x-card>
    @endif

    @if ($latestAttempt?->status !== 'submitted' || $latestAttempt === null)
        <form method="POST" action="{{ route('lms.student.quizzes.submit', [$course, $quiz]) }}" class="space-y-4">
            @csrf
            @foreach ($quiz->questions as $index => $question)
                <x-card>
                    <p class="font-semibold">{{ $index + 1 }}. {{ $question->prompt }}</p>
                    <div class="mt-4 space-y-2">
                        @if ($question->type === 'multiple_choice')
                            @foreach (($question->options ?? []) as $option)
                                <label class="flex items-start gap-3 rounded-lg border border-[var(--app-border)] p-3">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $option }}" class="mt-1">
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        @elseif ($question->type === 'true_false')
                            @foreach (['true' => 'Benar', 'false' => 'Salah'] as $value => $label)
                                <label class="flex items-center gap-3 rounded-lg border border-[var(--app-border)] p-3">
                                    <input type="radio" name="answers[{{ $question->id }}]" value="{{ $value }}">
                                    <span>{{ $label }}</span>
                                </label>
                            @endforeach
                        @else
                            <input type="text" name="answers[{{ $question->id }}]" class="input w-full" placeholder="Tulis jawaban">
                        @endif
                    </div>
                </x-card>
            @endforeach
            <button type="submit" class="btn btn-primary">Kirim jawaban</button>
        </form>
    @else
        <x-card>
            <p class="text-body text-[var(--app-text-muted)]">Percobaan kuis ini sudah dikumpulkan.</p>
        </x-card>
    @endif
</div>
@endsection
