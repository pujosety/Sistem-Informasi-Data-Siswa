@extends('public.layout')

@php
    $pageTitle = $post->metaTitle();
    $pageDescription = $post->meta_description ?: $post->excerpt;
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)

@section('body')
<article>
    <header class="bg-[var(--app-sidebar-bg)] text-white">
        <div class="mx-auto max-w-3xl px-5 sm:px-8 py-12">
            <a href="{{ route('public.news') }}"
               class="text-caption text-white/60 hover:text-white transition-colors">
                &larr; Semua berita
            </a>

            <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-white/60">
                @if ($post->category)
                    <span class="font-semibold text-white/85">{{ $post->category->name }}</span>
                @endif
                <time datetime="{{ $post->published_at?->toDateString() }}">
                    {{ $post->published_at?->translatedFormat('j F Y') }}
                </time>
            </div>

            <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold leading-tight tracking-tight">
                {{ $post->title }}
            </h1>

            @if ($post->author)
                <p class="mt-3 text-caption text-white/55">
                    {{ $post->author->name }}
                </p>
            @endif
        </div>
    </header>

    <div class="mx-auto max-w-3xl px-5 sm:px-8 py-12">
        {{--
            Escaped, deliberately.

            The body is written by a school administrator, and a CMS that
            renders it raw is one stored-XSS sink away: anyone who can reach
            the editor can put a script tag in a page every visitor loads, and
            that session belongs to a parent or a student.

            §58 requires CMS HTML to be sanitized. That needs a sanitiser at
            write time, not at render time, so this change renders escaped and
            the content-management side is where HTML gets cleaned. Until that
            exists, an author who needs formatting is better served by plain
            paragraphs than by an XSS hole.
        --}}
        <div class="prose-sida max-w-none whitespace-pre-line">{{ $post->body }}</div>

        @if ($post->tags->isNotEmpty())
            <ul class="mt-10 flex flex-wrap gap-2">
                @foreach ($post->tags as $tag)
                    <li class="text-caption px-3 py-1 rounded-full bg-[var(--app-surface-muted)]">
                        {{ $tag->name }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-3xl px-5 sm:px-8 pb-14">
            <h2 class="text-h3 font-semibold">Bacaan lain</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($related as $other)
                    <li>
                        <a href="{{ $other->url() }}"
                           class="text-body hover:text-[var(--app-primary)] transition-colors">
                            {{ $other->title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</article>
@endsection
