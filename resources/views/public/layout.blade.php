<!DOCTYPE html>
{{--
    Shared chrome for the public school website.

    Deliberately not the authenticated app shell. That shell assumes a session,
    a workspace and a role, and a public page has none of those; sharing it
    would mean every public view carried the machinery for something it can
    never show.

    The one thing shared with the rest of the app is the design system — the
    same tokens, the same components, the same fonts — so the public site and
    the portal look like one product.
--}}
@php
    $current = trim(parse_url(request()->path(), PHP_URL_PATH) ?? '/', '/');
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', $school['name'] ?: 'SIDA')</title>
    <meta name="description" content="@yield('description', 'Portal informasi dan administrasi ' . ($school['name'] ?: 'sekolah') . '.')">
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

    <header class="bg-[var(--app-sidebar-bg)] text-white">
        <div class="mx-auto max-w-6xl px-5 sm:px-8">
            <div class="flex items-center justify-between gap-4 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0 group">
                    <span class="grid place-items-center w-10 h-10 shrink-0 rounded-[var(--radius-lg)] bg-white">
                        <x-brand.logo variant="icon" height="h-8" alt="SIDA" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-body font-bold leading-tight truncate group-hover:underline">
                            {{ $school['name'] ?: 'SIDA' }}
                        </p>
                        <p class="text-[11px] text-white/55 leading-tight">Sistem Informasi Data Siswa</p>
                    </div>
                </a>

                <a href="{{ route('login') }}"
                   class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-[var(--radius-md)]
                          bg-white text-[var(--app-primary)] text-body font-semibold
                          hover:bg-white/90 transition-colors focus-visible:outline-2
                          focus-visible:outline-offset-2 focus-visible:outline-white">
                    <x-icon name="log-in" class="w-4 h-4" />
                    <span class="hidden sm:inline">Portal SIDA</span>
                </a>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('body')
    </main>

    {{--
        Footer navigation. Each link is conditional on the page existing, so a
        school running an older build never sees a 404 in its own footer.
    --}}
    <footer class="border-t border-[var(--app-border)] bg-[var(--app-surface)]">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 py-8">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-body font-bold text-[var(--app-text)]">{{ $school['name'] ?: 'SIDA' }}</p>
                    @if (filled($school['address']) || filled($school['city']))
                        <p class="mt-1 text-caption text-[var(--app-text-muted)] max-w-sm">
                            {{ collect([$school['address'], $school['city'], $school['province']])->filter()->join(', ') }}
                        </p>
                    @endif
                </div>

                <nav aria-label="Halaman publik" class="flex flex-wrap gap-x-6 gap-y-2 text-caption">
                    <a href="{{ route('home') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => $current === '', 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => $current !== ''])>
                        Beranda
                    </a>
                    <a href="{{ route('public.about') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => $current === 'tentang', 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => $current !== 'tentang'])>
                        Profil
                    </a>
                    <a href="{{ route('public.news') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => str_starts_with($current, 'berita'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! str_starts_with($current, 'berita')])>
                        Berita
                    </a>
                    <a href="{{ route('public.programs') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => $current === 'program', 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => $current !== 'program'])>
                        Program
                    </a>
                    <a href="{{ route('public.admission') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => $current === 'ppdb', 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => $current !== 'ppdb'])>
                        PPDB
                    </a>
                    <a href="{{ route('public.contact') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => $current === 'kontak', 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => $current !== 'kontak'])>
                        Kontak
                    </a>
                </nav>
            </div>

            <p class="mt-6 pt-5 border-t border-[var(--app-border)] text-caption text-[var(--app-text-muted)]">
                © {{ now()->year }} {{ $school['name'] ?: 'SIDA' }}
                @if (filled($school['npsn'])) · NPSN {{ $school['npsn'] }}@endif
            </p>
        </div>
    </footer>
</div>
</body>
</html>
