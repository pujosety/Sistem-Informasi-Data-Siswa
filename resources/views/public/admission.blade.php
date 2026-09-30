@extends('public.layout')

@php $title = 'PPDB · ' . ($school['name'] ?: 'SIDA'); @endphp
@section('title', $title)
@php $description = 'Pendaftaran siswa baru ' . ($school['name'] ?: 'sekolah') . ' ' . ($figures['academicYear'] ?? '') . '.'; @endphp
@section('description', $description)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">
            PPDB{{ $figures['academicYear'] ? ' · ' . $figures['academicYear'] : '' }}
        </p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Pendaftaran siswa baru
        </h1>
        <p class="mt-4 text-body text-white/70 max-w-2xl leading-relaxed">
            Pendaftaran dilakukan bertahap. Data yang diisi akan diverifikasi
            sebelum dikonfirmasi, dan statusnya dapat dipantau melalui portal.
        </p>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
    {{--
        The steps, stated plainly. A family deciding whether to apply needs to
        know what will be asked of them and what happens next; that belongs on
        the page rather than behind the form.
    --}}
    <ol class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Isi formulir', 'Data siswa dan orang tua/wali.'],
            ['Unggah berkas', 'KK, akta kelahiran, dan pas foto.'],
            ['Verifikasi', ['Petugas memeriksa kelengkapan berkas.','Dokumen yang tidak sah diperbaiki.']],
            ['Konfirmasi', ['Status penerimaan dapat dipantau','lewat akun pendaftar.']],
        ] as $index => [$title, $detail])
            <li class="surface p-5">
                <span class="grid place-items-center w-7 h-7 rounded-full bg-[var(--app-primary)] text-white text-caption font-bold">
                    {{ $index + 1 }}
                </span>
                <p class="mt-3 text-body font-semibold">{{ $title }}</p>
                @foreach ((array) $detail as $line)
                    <p class="mt-1 text-caption text-[var(--app-text-muted)]">{{ $line }}</p>
                @endforeach
            </li>
        @endforeach
    </ol>

    <div class="mt-10 flex flex-wrap gap-3">
        <a href="{{ route('register') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                  bg-[var(--app-primary)] text-white text-body font-semibold
                  hover:opacity-90 transition-opacity focus-visible:outline-2
                  focus-visible:outline-offset-2 focus-visible:outline-[var(--app-primary)]">
            Mulai pendaftaran
            <x-icon name="arrow-right" class="w-4 h-4" />
        </a>
        <a href="{{ route('public.contact') }}"
           class="inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                  border border-[var(--app-border)] text-[var(--app-text)] text-body font-medium
                  hover:bg-[var(--app-surface-muted)] transition-colors">
            Tanya lebih dulu
        </a>
    </div>

    <p class="mt-6 text-caption text-[var(--app-text-muted)]">
        Sudah mendaftar? Masuk dengan akun Anda melalui
        <a href="{{ route('login') }}" class="text-[var(--app-primary)] hover:underline">Portal SIDA</a>.
    </p>
</section>
@endsection
