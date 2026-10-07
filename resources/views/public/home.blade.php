@extends('public.layout')

@section('title', $school['name'] ?? config('branding.platform.name'))
@section('description', 'Sekolah modern dengan program unggulan, dan lingkungan belajar yang mendukung perkembangan siswa.')

{{--
    THE LANDING PAGE IS A LOOP, NOT A PAGE.

    Every section below comes from the `landing_sections` table: its type, its
    copy, its image, its position and whether it is enabled at all. This file
    contains no headings, no statistics and no calls to action.

    That is the whole point of brief §CMS. An administrator reorders sections,
    switches one off, rewrites the hero headline and swaps a photograph from
    the CMS without anyone opening a code editor — and a block type that does
    not exist in the front end is skipped rather than fatal, because these rows
    are data and data can be wrong.
--}}

@section('body')

    @forelse ($sections as $section)
        @include('public.blocks.block', ['section' => $section])
    @empty
        {{-- An empty page is a legitimate state — the CMS has not been set up
             yet, or every block was disabled. Say so plainly, with the way
             back in, rather than rendering a hero-shaped void. --}}
        <section class="bg-[var(--app-bg)]">
            <div class="mx-auto max-w-3xl px-5 py-28 text-center">
                <img src="{{ data_get($brand ?? [], 'logo') ?: asset(config('branding.assets.logo')) }}"
                     alt="{{ data_get($brand ?? [], 'shortName') ?: config('branding.platform.name') }}"
                     class="mx-auto h-14 w-auto opacity-80">

                <h1 class="mt-8 text-display font-bold tracking-tight text-[var(--app-text)]">
                    {{ $school['name'] ?? config('branding.platform.name') }}
                </h1>

                <p class="mx-auto mt-4 max-w-xl text-body leading-relaxed text-[var(--app-text-muted)]">
                    Halaman utama belum diisi. Administrator dapat menambah dan
                    mengurutkan bagian halaman melalui CMS.
                </p>

                <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ route('public.admission') }}" class="btn btn-primary justify-center">
                        Pendaftaran Peserta Didik Baru
                    </a>
                    <a href="{{ route('public.about') }}" class="btn btn-secondary justify-center">
                        Tentang Sekolah
                    </a>
                </div>
            </div>
        </section>
    @endforelse
@endsection