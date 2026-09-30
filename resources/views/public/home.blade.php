<!DOCTYPE html>
{{--
    The public school website.

    §10 is the constraint that shapes this file: internal data must never
    become public by accident. So there is no student listing, no class roster,
    no announcement feed. The controller hands over a school profile and a few
    counts, and this view can only arrange those.

    When the CMS lands in PHASE 3 this becomes the template that renders
    blocks; the figures below are the hard-coded fallback a school sees before
    anyone has written a page for it.
--}}
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $school['name'] ?: 'SIDA' }}</title>
    <meta name="description" content="Portal informasi dan administrasi {{ $school['name'] ?: 'sekolah' }}. Pendaftaran siswa, akademik, dan documentasi dalam satu sistem.">
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/apple-touch-icon.png') }}">
    <meta name="theme-color" content="#0b3375">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[var(--app-bg)]">
<div class="min-h-full flex flex-col">

    {{-- Header: the school name is the identity, the portal link is the only action --}}
    <header class="bg-[var(--app-sidebar-bg)] text-white">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="flex items-center justify-between gap-4 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="grid place-items-center w-10 h-10 shrink-0 rounded-[var(--radius-lg)] bg-white">
                        <x-brand.logo variant="icon" height="h-8" alt="SIDA" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-body font-bold leading-tight truncate">
                            {{ $school['name'] ?: 'SIDA' }}
                        </p>
                        <p class="text-[11px] text-white/55 leading-tight">Sistem Informasi Data Siswa</p>
                    </div>
                </div>

                <a href="{{ $portalUrl }}"
                   class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius-md)]
                          bg-white text-[var(--app-primary)] text-body font-semibold
                          hover:bg-white/90 transition-colors focus-visible:outline-2
                          focus-visible:outline-offset-2 focus-visible:outline-white">
                    <x-icon name="log-in" class="w-4 h-4" />
                    Portal SIDA
                </a>
            </div>
        </div>
    </header>

    {{-- Hero: what the school is, and the two doors in --}}
    <section class="relative overflow-hidden bg-[var(--app-sidebar-bg)] text-white">
        <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
             style="background-image:radial-gradient(circle at 22% 18%, white 0, transparent 42%),radial-gradient(circle at 78% 82%, white 0, transparent 46%)"></div>

        <div class="relative mx-auto max-w-6xl px-5 sm:px-8 py-16 lg:py-24">
            <p class="text-caption uppercase tracking-[0.18em] text-white/50">
                {{ $figures['academicYear'] ?? 'Portal sekolah' }}
            </p>
            <h1 class="mt-3 font-[var(--font-display)] text-4xl sm:text-5xl font-extrabold leading-[1.12] tracking-tight max-w-3xl">
                Pendaftaran, akademik, dan<br class="hidden sm:block"> documentasi siswa.
            </h1>
            <p class="mt-5 text-body text-white/70 leading-relaxed max-w-2xl">
                Seluruh proses administrasi berjalan pada satu portal: dari pendaftaran
                dan verifikasi berkas hingga pencatatan akademik.
            </p>

            <div class="mt-8 flex flex-wrap gap-3">
                {{-- Registration is open to anyone; the portal is not. --}}
                <a href="{{ route('register') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                          bg-white text-[var(--app-primary)] text-body font-semibold
                          hover:bg-white/90 transition-colors focus-visible:outline-2
                          focus-visible:outline-offset-2 focus-visible:outline-white">
                    Pendaftaran Siswa Baru
                    <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
                <a href="{{ $portalUrl }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-[var(--radius-md)]
                          border border-white/25 text-white text-body font-medium
                          hover:bg-white/10 transition-colors focus-visible:outline-2
                          focus-visible:outline-offset-2 focus-visible:outline-white">
                    Masuk ke Portal
                </a>
            </div>
        </div>
    </section>

    {{-- Figures. Counts only — there is deliberately no link from here into any
         record, because a count that can be clicked through is a listing. --}}
    <section class="mx-auto w-full max-w-6xl px-5 sm:px-8 py-12">
        <h2 class="text-h2 font-semibold">Sekolah dalam angka</h2>
        <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
            Ringkasan tingkat sekolah. Data pribadi siswa tidak dimuat di halaman ini.
        </p>

        <dl class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ([
                ['Siswa', $figures['students']],
                ['Kelas aktif', $figures['classes']],
                ['Mata pelajaran', $figures['subjects']],
                ['Tahun ajaran', $figures['academicYear'] ?? '—'],
            ] as [$label, $value])
                <div class="surface p-5">
                    <dt class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">
                        {{ $label }}
                    </dt>
                    <dd class="mt-2 font-[var(--font-display)] text-3xl font-bold text-[var(--app-text)]">
                        {{ is_int($value) ? number_format($value, 0, ',', '.') : $value }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    {{-- Contact, from the school's own published settings. Each field is
         conditional because a school that has not filled one in should not
         render an empty label. --}}
    @if (filled($school['address']) || filled($school['city']) || filled($school['email']) || filled($school['phone']))
        <section class="mx-auto w-full max-w-6xl px-5 sm:px-8 pb-14">
            <h2 class="text-h2 font-semibold">Kontak</h2>

            <dl class="mt-5 grid sm:grid-cols-2 gap-x-8 gap-y-4 text-body">
                @if (filled($school['address']))
                    <div>
                        <dt class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Alamat</dt>
                        <dd class="mt-1">{{ $school['address'] }}</dd>
                    </div>
                @endif

                @if (filled($school['city']))
                    <div>
                        <dt class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Wilayah</dt>
                        <dd class="mt-1">
                            {{ $school['city'] }}@if (filled($school['province'])), {{ $school['province'] }}@endif
                        </dd>
                    </div>
                @endif

                @if (filled($school['email']))
                    <div>
                        <dt class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Email</dt>
                        {{-- mailto rather than a bare address: a school that pastes a
                             link is easier to phish, and this is one field. --}}
                        <dd class="mt-1">
                            <a href="mailto:{{ $school['email'] }}" class="text-[var(--app-primary)] hover:underline">{{ $school['email'] }}</a>
                        </dd>
                    </div>
                @endif

                @if (filled($school['phone']))
                    <div>
                        <dt class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">Telepon</dt>
                        <dd class="mt-1">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $school['phone']) }}" class="text-[var(--app-primary)] hover:underline">{{ $school['phone'] }}</a>
                        </dd>
                    </div>
                @endif
            </dl>
        </section>
    @endif

    <footer class="mt-auto border-t border-[var(--app-border)]">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 py-6 flex flex-wrap items-center justify-between gap-3">
            <p class="text-caption text-[var(--app-text-muted)]">
                © {{ now()->year }} {{ $school['name'] ?: 'SIDA' }}
                @if (filled($school['npsn'])) · NPSN {{ $school['npsn'] }}@endif
            </p>
            <a href="{{ $portalUrl }}" class="text-caption font-semibold text-[var(--app-primary)] hover:underline">
                Masuk
            </a>
        </div>
    </footer>
</div>
</body>
</html>
