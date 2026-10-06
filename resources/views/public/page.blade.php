@extends('public.layout')

@php
    $pageTitle = $page->metaTitle();
    $pageDescription = $page->meta_description ?: $page->excerpt;
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)

@section('body')
<article>
    <header class="bg-[var(--app-sidebar-bg)] text-white">
        <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
            <nav aria-label="Breadcrumb" class="text-caption text-white/60">
                <a href="{{ url('/') }}" class="hover:text-white">Beranda</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <a href="{{ route('public.about') }}" class="hover:text-white">Profil</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span aria-current="page">{{ $page->title }}</span>
            </nav>

            <h1 class="mt-5 font-[var(--font-display)] text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
                {{ $page->title }}
            </h1>
            @if ($page->author)
                <p class="mt-3 text-caption text-white/55">{{ $page->author->name }}</p>
            @endif
        </div>
    </header>

    <div class="mx-auto max-w-3xl px-5 py-12 sm:px-8">
        @if ($page->media->isNotEmpty())
            @php($cover = $page->media->first())
            <figure class="mb-10 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
                <img src="{{ $cover->url() }}" alt="{{ $cover->alt_text ?: $page->title }}" class="aspect-[16/9] w-full object-cover" loading="eager" decoding="async">
            </figure>
        @endif

        @if (filled($page->excerpt))
            <p class="mb-8 text-xl leading-relaxed text-[var(--app-text-muted)]">{{ $page->excerpt }}</p>
        @endif

        <div class="prose-sida max-w-none">{!! $page->body !!}</div>
    </div>
</article>
@endsection
