@extends('public.layout')

@php
    $pageTitle = 'Berita · ' . ($school['name'] ?: config('branding.platform.name'));
    $pageDescription = 'Kabar dan pengumuman ' . ($school['name'] ?: 'sekolah') . '.';
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Kabar sekolah</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Berita
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
    <div class="grid items-center gap-8 lg:grid-cols-[1.1fr_0.9fr]">
        <div>
            <p class="text-small font-semibold text-[var(--app-primary)]">Ada cerita apa hari ini?</p>
            <h2 class="mt-2 text-h1 font-bold tracking-tight">Kabar terbaru dari sekolah.</h2>
            <p class="mt-4 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                Baca informasi, kegiatan, dan cerita yang sudah dipublikasikan oleh tim sekolah.
            </p>
        </div>
        <x-image-placeholder type="news" aspect="16/10" alt="Siswa SMP 1 LYFLA mengikuti kegiatan belajar" />
    </div>

    <div class="mt-12">
        <nav aria-label="Filter kategori" class="mb-8 flex flex-wrap items-center gap-2">
            <span class="mr-1 self-center text-caption text-[var(--app-text-muted)]">Kategori:</span>
            <a href="{{ route('public.news') }}"
               @class([
                   'rounded-full px-3 py-1 text-caption transition-colors',
                   'bg-[var(--app-primary)] text-white' => $categorySlug === '',
                   'bg-[var(--app-surface-muted)] hover:bg-[var(--public-soft)]' => $categorySlug !== '',
               ])
               @if ($categorySlug === '') aria-current="page" @endif>
                Semua
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('public.news', ['category' => $category->slug]) }}"
                   @class([
                       'rounded-full px-3 py-1 text-caption transition-colors',
                       'bg-[var(--app-primary)] text-white' => $categorySlug === $category->slug,
                       'bg-[var(--app-surface-muted)] hover:bg-[var(--public-soft)]' => $categorySlug !== $category->slug,
                   ])
                   @if ($categorySlug === $category->slug) aria-current="page" @endif>
                    {{ $category->name }}
                </a>
            @endforeach
        </nav>

    @forelse ($posts as $post)
        {{--
            $post is already filtered by publishedAndPublic() in the
            controller, so reaching this loop means the post is public. There
            is no second check here: a second check is where one of the two
            conditions eventually gets forgotten, and the symptom would be a
            news page that is mysteriously empty.
        --}}
        <article class="mb-8 last:mb-0">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-[var(--app-text-muted)]">
                @if ($post->category)
                    <span class="font-semibold text-[var(--app-primary)]">{{ $post->category->name }}</span>
                @endif
                <time datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->translatedFormat('j F Y') }}
                </time>
            </div>

            <h2 class="mt-1.5 text-h2 font-semibold leading-snug">
                <a href="{{ $post->url() }}"
                   class="hover:text-[var(--app-primary)] transition-colors">{{ $post->title }}</a>
            </h2>

            @if (filled($post->excerpt))
                <p class="mt-2 text-body text-[var(--app-text-muted)] leading-relaxed">{{ $post->excerpt }}</p>
            @endif
        </article>
    @empty
        {{-- The honest empty state. A CMS with nothing published should say so,
             not show a broken layout. --}}
        <div class="surface p-8 text-center">
            <p class="text-body font-semibold">Belum ada berita</p>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Administrator sekolah dapat menerbitkannya melalui
                <span class="font-mono text-[13px]">Website → Artikel</span>.
            </p>
        </div>
    @endforelse

    @if ($posts->hasPages())
        <nav class="mt-10 flex justify-center" aria-label="Navigasi halaman">
            {{ $posts->links() }}
        </nav>
    @endif
    </div>
</section>
@endsection
