@extends('public.layout')

@php
    $pageTitle = $query !== ''
        ? 'Hasil pencarian: '.$query.' · '.($school['name'] ?: config('branding.platform.name'))
        : 'Cari informasi · '.($school['name'] ?: config('branding.platform.name'));
    $pageDescription = 'Cari berita dan program di '.($school['name'] ?: 'LYFLA').'.';
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)

@section('body')
<section class="relative overflow-hidden bg-[var(--public-primary)] text-white">
    <div class="pointer-events-none absolute -right-24 -top-24 size-72 rounded-full border border-white/15" aria-hidden="true"></div>
    <div class="shell-wide relative py-16 sm:py-20">
        <p class="text-small font-semibold text-white/70">Cari informasi sekolah</p>
        <h1 class="mt-3 max-w-3xl text-display font-bold leading-tight tracking-tight text-balance text-white">
            Temukan yang kamu cari.
        </h1>
        <form action="{{ route('public.search') }}" method="GET" class="mt-8 flex max-w-3xl flex-col gap-3 sm:flex-row">
            <label for="public-search" class="sr-only">Kata kunci pencarian</label>
            <input id="public-search" name="q" value="{{ $query }}" type="search"
                   placeholder="Cari berita, program, atau informasi..."
                   class="min-h-12 flex-1 rounded-[var(--radius-md)] border border-white/20 bg-white px-4 text-body text-[var(--app-text)] placeholder:text-[var(--app-text-subtle)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                   autocomplete="off">
            <button type="submit" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-[var(--radius-md)] bg-[var(--public-accent)] px-5 text-body font-semibold text-white transition hover:brightness-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                <x-icon name="search" class="size-5" />
                Cari
            </button>
        </form>
    </div>
</section>

<section class="shell-wide py-12 sm:py-16" aria-live="polite">
    @if ($query === '')
        <div class="surface overflow-hidden p-8 sm:p-12">
            <div class="max-w-2xl">
                <span class="grid size-12 place-items-center rounded-[var(--radius-md)] bg-[var(--public-soft)] text-[var(--public-primary)]">
                    <x-icon name="sparkles" class="size-6" />
                </span>
                <h2 class="mt-5 text-h1 font-bold text-[var(--app-text)]">Mulai dari satu kata.</h2>
                <p class="mt-3 max-w-xl text-body leading-relaxed text-[var(--app-text-muted)]">Cari berita, program sekolah, atau kabar terbaru yang ingin kamu kenali lebih dekat.</p>
            </div>
        </div>
    @elseif (mb_strlen($query) < 2)
        <div class="surface p-8 text-center">
            <p class="text-body font-semibold text-[var(--app-text)]">Masukkan kata kunci</p>
            <p class="mt-2 text-body text-[var(--app-text-muted)]">Gunakan minimal dua karakter agar hasilnya lebih relevan.</p>
        </div>
    @else
        @php($resultCount = $posts->count() + $programs->count())
        <div class="flex flex-col gap-2 border-b border-[var(--app-border)] pb-5 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-small font-semibold text-[var(--public-primary)]">Hasil pencarian</p>
                <h2 class="mt-1 text-h1 font-bold text-[var(--app-text)]">“{{ $query }}”</h2>
            </div>
            <p class="text-small text-[var(--app-text-muted)]">{{ $resultCount }} hasil ditemukan</p>
        </div>

        @if ($programs->isNotEmpty())
            <section class="mt-8" aria-labelledby="program-results">
                <div class="flex items-center justify-between gap-4">
                    <h3 id="program-results" class="text-h2 font-bold text-[var(--app-text)]">Program</h3>
                    <a href="{{ route('public.programs') }}" class="text-small font-semibold text-[var(--public-primary)] hover:underline">Lihat semua</a>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($programs as $program)
                        <a href="{{ route('public.programs') }}#program-{{ $program->id }}" class="group surface p-5 transition hover:-translate-y-0.5 hover:shadow-[var(--shadow-raised)]">
                            <span class="grid size-10 place-items-center rounded-[var(--radius-md)] bg-[var(--public-soft)] text-[var(--public-primary)]"><x-icon name="layers-3" class="size-5" /></span>
                            <h4 class="mt-4 text-h3 font-bold text-[var(--app-text)] group-hover:text-[var(--public-primary)]">{{ $program->name }}</h4>
                            <p class="mt-1 text-small text-[var(--app-text-muted)]">Program {{ $program->code }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($posts->isNotEmpty())
            <section class="mt-12" aria-labelledby="news-results">
                <div class="flex items-center justify-between gap-4">
                    <h3 id="news-results" class="text-h2 font-bold text-[var(--app-text)]">Berita</h3>
                    <a href="{{ route('public.news') }}" class="text-small font-semibold text-[var(--public-primary)] hover:underline">Lihat semua</a>
                </div>
                <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        <article class="group overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
                            <div class="h-2 bg-[var(--public-accent)]"></div>
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-wrap items-center gap-2 text-caption text-[var(--app-text-muted)]">
                                    @if ($post->category)<span class="font-semibold text-[var(--public-primary)]">{{ $post->category->name }}</span>@endif
                                    @if ($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j F Y') }}</time>@endif
                                </div>
                                <h4 class="mt-3 text-h3 font-bold leading-snug text-[var(--app-text)] group-hover:text-[var(--public-primary)]"><a href="{{ $post->url() }}">{{ $post->title }}</a></h4>
                                @if ($post->excerpt)<p class="mt-2 line-clamp-3 text-small leading-relaxed text-[var(--app-text-muted)]">{{ $post->excerpt }}</p>@endif
                                <a href="{{ $post->url() }}" class="mt-5 inline-flex items-center gap-1.5 text-small font-semibold text-[var(--public-primary)]">Baca berita <x-icon name="arrow-up-right" class="size-4" /></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($resultCount === 0)
            <div class="mt-8 surface p-8 text-center">
                <span class="mx-auto grid size-12 place-items-center rounded-full bg-[var(--public-soft)] text-[var(--public-primary)]"><x-icon name="search-x" class="size-6" /></span>
                <h3 class="mt-4 text-h2 font-bold text-[var(--app-text)]">Belum menemukan hasil.</h3>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">Coba kata kunci lain atau lihat seluruh berita dan program yang tersedia.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('public.news') }}" class="btn btn-secondary">Lihat Berita</a>
                    <a href="{{ route('public.programs') }}" class="btn btn-primary">Lihat Program</a>
                </div>
            </div>
        @endif
    @endif
</section>
@endsection
