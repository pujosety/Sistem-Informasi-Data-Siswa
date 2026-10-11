@extends('components.app-shell')

@php($isTeacher = auth()->user()->can('lms.lesson.manage'))
@section('title', 'LMS · Diskusi')
@section('page-title', 'Diskusi Course')
@section('page-description', $course->title)

@section('content')
<div class="max-w-4xl space-y-5">
    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Forum diskusi</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Tanya jawab dan kolaborasi peserta course.</p>
            </div>
            <a href="{{ $isTeacher ? route('lms.teacher.courses.show', $course) : route('lms.student.courses.show', $course) }}" class="btn btn-secondary btn-sm">Kembali ke course</a>
        </div>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route($isTeacher ? 'lms.teacher.courses.discussions.store' : 'lms.student.courses.discussions.store', $course) }}" class="space-y-3">
            @csrf
            <label for="body" class="block text-small font-medium">Tulis pesan baru</label>
            <textarea id="body" name="body" rows="4" class="input" required maxlength="10000" placeholder="Tulis pertanyaan atau tanggapan..."></textarea>
            @error('body')<p class="text-caption text-red-600">{{ $message }}</p>@enderror
            <div class="flex justify-end"><button class="btn btn-primary">Kirim diskusi</button></div>
        </form>
    </x-card>

    @forelse ($discussions as $discussion)
        <x-card>
            <article>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold">{{ $discussion->author?->name ?? $discussion->author?->email ?? 'Pengguna' }}</p>
                        <time class="text-caption text-[var(--app-text-subtle)]">{{ $discussion->created_at?->format('d M Y H:i') }}</time>
                    </div>
                </div>
                <p class="mt-3 whitespace-pre-line text-body">{{ $discussion->body }}</p>

                <div class="mt-4 space-y-3 border-l-2 border-[var(--app-border)] pl-4">
                    @foreach ($discussion->replies as $reply)
                        <div>
                            <p class="font-medium">{{ $reply->author?->name ?? $reply->author?->email ?? 'Pengguna' }}</p>
                            <p class="mt-1 whitespace-pre-line text-small">{{ $reply->body }}</p>
                            <time class="text-caption text-[var(--app-text-subtle)]">{{ $reply->created_at?->format('d M Y H:i') }}</time>
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route($isTeacher ? 'lms.teacher.courses.discussions.store' : 'lms.student.courses.discussions.store', $course) }}" class="mt-4 flex gap-2">
                    @csrf
                    <input type="hidden" name="parent_id" value="{{ $discussion->id }}">
                    <input name="body" class="input" maxlength="10000" required placeholder="Balas diskusi ini...">
                    <button class="btn btn-secondary">Balas</button>
                </form>
            </article>
        </x-card>
    @empty
        <x-card><p class="text-small text-[var(--app-text-muted)]">Belum ada diskusi. Jadilah yang pertama bertanya.</p></x-card>
    @endforelse
</div>
@endsection
