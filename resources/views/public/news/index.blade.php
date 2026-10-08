@extends('public.layout')

@php
    $pageTitle = 'Berita · ' . ($school['name'] ?: config('branding.platform.name'));
    $pageDescription = 'Kabar dan pengumuman ' . ($school['name'] ?: 'sekolah') . '.';
@endphp
@section('title', $pageTitle)
@section('description', $pageDescription)

@section('body')
@php
    $categoryImages = [
        'prestasi' => asset('images/schools/smpn4metro/landing/achievement.jpg'),
        'kegiatan-siswa' => asset('images/schools/smpn4metro/landing/experience-pentas.jpg'),
        'pengumuman' => asset('images/schools/smpn4metro/landing/ppdb-feature.jpg'),
        'berita-sekolah' => asset('images/schools/smpn4metro/landing/facility-community.jpg'),
    ];
@endphp
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Kabar sekolah</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Berita
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-14 sm:py-20">
    <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <p class="text-small font-semibold uppercase tracking-[0.16em] text-[var(--app-primary)]">Ada cerita apa hari ini?</p>
            <h2 class="mt-3 text-h1 font-bold tracking-tight text-balance">Kabar terbaru dari sekolah.</h2>
            <p class="mt-5 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                Baca informasi, kegiatan, dan cerita yang sudah dipublikasikan oleh tim sekolah.
            </p>
            <div class="mt-7 inline-flex items-center gap-2 rounded-full bg-[var(--public-soft)] px-3.5 py-2 text-small font-semibold text-[var(--app-primary)]">
                <span class="size-2 rounded-full bg-[var(--brand-gold)]"></span>
                Cerita nyata dari lingkungan sekolah
            </div>
        </div>
        <figure class="group relative overflow-hidden rounded-[1.5rem] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-xl">
            <img src="{{ asset('images/schools/smpn4metro/landing/ppdb-feature.jpg') }}"
                 alt="Kegiatan PPDB SMP Negeri 4 Metro"
                 class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                 loading="eager" decoding="async">
            <figcaption class="absolute inset-x-4 bottom-4 rounded-xl border border-white/20 bg-black/45 px-4 py-3 text-small text-white backdrop-blur-md">
                Informasi, prestasi, dan kegiatan siswa
            </figcaption>
        </figure>
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
        @php
            $image = $post->media->first()?->url()
                ?: ($categoryImages[$post->category?->slug ?? ''] ?? asset('images/schools/smpn4metro/landing/facility-community.jpg'));
        @endphp
        <article class="group mb-6 overflow-hidden rounded-[1.25rem] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-xl last:mb-0">
            <div class="grid md:grid-cols-[15rem_1fr]">
                <a href="{{ $post->url() }}" class="block overflow-hidden bg-[var(--app-surface-muted)]">
                    <img src="{{ $image }}" alt="{{ $post->title }}" class="aspect-[16/10] h-full w-full object-cover transition duration-700 group-hover:scale-[1.04]" loading="lazy">
                </a>
                <div class="p-5 sm:p-6">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-[var(--app-text-muted)]">
                        @if ($post->category)
                            <span class="rounded-full bg-[var(--public-soft)] px-2.5 py-1 font-semibold text-[var(--app-primary)]">{{ $post->category->name }}</span>
                        @endif
                        <time datetime="{{ $post->published_at?->toDateString() }}">
                            {{ $post->published_at?->translatedFormat('j F Y') }}
                        </time>
                    </div>

                    <h2 class="mt-3 text-h2 font-semibold leading-snug text-[var(--app-text)]">
                        <a href="{{ $post->url() }}" class="transition-colors hover:text-[var(--app-primary)]">{{ $post->title }}</a>
                    </h2>

                    @if (filled($post->excerpt))
                        <p class="mt-2 line-clamp-3 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $post->excerpt }}</p>
                    @endif

                    <a href="{{ $post->url() }}" class="mt-5 inline-flex items-center gap-2 text-small font-semibold text-[var(--app-primary)]">
                        Baca selengkapnya
                        <x-icon name="arrow-right" class="size-4 transition-transform group-hover:translate-x-1" />
                    </a>
                </div>
            </div>
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
