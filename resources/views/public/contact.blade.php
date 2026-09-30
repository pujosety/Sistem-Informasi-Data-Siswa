@extends('public.layout')

@php $title = 'Kontak · ' . ($school['name'] ?: 'SIDA'); @endphp
@section('title', $title)
@php $description = 'Kontak dan lokasi ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $description)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Kontak</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Hubungi kami
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
    @if (filled($school['address']) || filled($school['city']) || filled($school['email']) || filled($school['phone']))
        <div class="grid gap-6 sm:grid-cols-2">
            @if (filled($school['address']) || filled($school['city']))
                <div class="surface p-6">
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Alamat</p>
                    <address class="mt-2 text-body not-italic leading-relaxed">
                        {{ $school['address'] }}@if (filled($school['city'])),
                            {{ $school['city'] }}@if (filled($school['province'])), {{ $school['province'] }}@endif
                        @endif
                    </address>
                </div>
            @endif

            <div class="surface p-6">
                @if (filled($school['phone']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Telepon</p>
                    {{-- tel: carries only the dialled characters; a formatted
                         number pasted into a URI is a silent failure. --}}
                    <p class="mt-2 text-body">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school['phone']) }}"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['phone'] }}</a>
                    </p>
                @endif

                @if (filled($school['email']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)] mt-5">Email</p>
                    <p class="mt-2 text-body break-words">
                        <a href="mailto:{{ $school['email'] }}"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['email'] }}</a>
                    </p>
                @endif

                @if (filled($school['website']))
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)] mt-5">Situs web</p>
                    <p class="mt-2 text-body break-words">
                        {{-- A school that typed a bare host gets https added, so
                             the link works; a school that typed a scheme keeps it. --}}
                        @php $href = preg_match('#^https?://#i', $school['website']) ? $school['website'] : 'https://' . $school['website']; @endphp
                        <a href="{{ $href }}" rel="noopener" target="_blank"
                           class="text-[var(--app-primary)] hover:underline">{{ $school['website'] }}</a>
                    </p>
                @endif
            </div>
        </div>

        <div class="mt-10 rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-6">
            <h2 class="text-h3 font-semibold">Tertarik mendaftar?</h2>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Pendaftaran siswa baru dilakukan melalui portal PPDB.
            </p>
            <a href="{{ route('public.admission') }}"
               class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                      bg-[var(--app-primary)] text-white text-body font-semibold
                      hover:opacity-90 transition-opacity focus-visible:outline-2
                      focus-visible:outline-offset-2 focus-visible:outline-[var(--app-primary)]">
                Lihat PPDB
                <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        </div>
    @else
        {{-- Nothing published yet. Better than an empty grid of labels. --}}
        <div class="surface p-8 text-center">
            <p class="text-body font-semibold">Kontak belum dilengkapi</p>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Administrator sekolah dapat mengisinya melalui
                <span class="font-mono text-[13px]">Pengaturan → Sekolah</span>.
            </p>
            <a href="{{ route('public.admission') }}"
               class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                      bg-[var(--app-primary)] text-white text-body font-semibold hover:opacity-90">
                Lihat PPDB
            </a>
        </div>
    @endif
</section>
@endsection
