@extends('public.layout')

@php
    $pageTitle = $post->metaTitle();
    $pageDescription = $post->meta_description ?: $post->excerpt ?: 'Kabar terbaru dari '.($school['name'] ?: 'sekolah').'.';
    $pageImage = $post->media->first()?->url() ?: (data_get($brand ?? [], 'logo') ?: asset(config('branding.assets.logo')));
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)
@section('og_type', 'article')
@section('og_image', $pageImage)

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
            Rendered, because the stored value is already sanitised.

            CmsPostService cleans the body on the way IN — create, update and
            restore all pass through HTMLPurifier — so this is not a raw
            author string reaching an unescaped sink. It is the only way an
            editor can use a heading or a link.

            The {!! !!} here is safe BECAUSE of that, and the dependency is
            recorded in CmsContentSanitizer. If the sanitiser is ever removed
            from the write path, this line becomes the XSS it was written to
            avoid; CmsSanitisationTest fails in that case and says so.
        --}}
        <div class="prose-sida max-w-none">{!! $post->body !!}</div>

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
