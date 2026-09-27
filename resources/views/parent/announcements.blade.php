@extends('components.app-shell')

@section('title', 'Pengumuman '.$student->full_name)
@section('page-title', 'Pengumuman')
@section('page-description', $student->full_name . ($enrollment?->classroom ? ' · '.$enrollment->classroom->name : ''))

@section('content')
<div class="space-y-3 max-w-3xl">

    @if (! $announcements)
        <x-empty-state
            icon="megaphone"
            title="Belum ada pengumuman"
            description="Pengumuman kelas untuk anak Anda akan tampil di sini." />
    @else
        @forelse ($announcements as $a)
            <article class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h2 class="text-body font-semibold">{{ $a->title }}</h2>
                    <span class="text-caption text-[var(--app-text-muted)]">
                        {{ $a->published_at?->translatedFormat('d M Y') }}
                    </span>
                </div>
                <p class="mt-2 whitespace-pre-line text-small text-[var(--app-text-secondary)]">{{ $a->body }}</p>
            </article>
        @empty
            <x-empty-state
                icon="megaphone"
                title="Belum ada pengumuman"
                description="Pengumuman kelas untuk anak Anda akan tampil di sini." />
        @endforelse

        <div>{{ $announcements->links() }}</div>
    @endif
</div>
@endsection
