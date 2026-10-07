@php
    $runtimeBrandCss = app(\App\Services\BrandService::class)->cssVariables(
        data_get($brand ?? [], 'primary'),
        data_get($brand ?? [], 'accent'),
        data_get($brand ?? [], 'tokens', []),
    );
    $brandLogo = data_get($brand ?? [], 'logo') ?: asset(config('branding.assets.logo'));
    $brandIcon = data_get($brand ?? [], 'icon') ?: $brandLogo;
    $brandFavicon = data_get($brand ?? [], 'assets.favicon') ?: asset('branding/favicon-32x32.png');
    $brandAppIcon = data_get($brand ?? [], 'assets.appIcon') ?: asset('branding/apple-touch-icon.png');
@endphp

<!DOCTYPE html>
<html lang="id" class="h-full" style="{{ $runtimeBrandCss }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title', $school['name'] ?: config('branding.platform.name'))</title>
    <meta name="description" content="@yield('description', 'Portal informasi dan administrasi ' . ($school['name'] ?: 'sekolah') . '.')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ $school['name'] ?: config('branding.platform.name') }}">
    <meta property="og:title" content="@yield('title', $school['name'] ?: config('branding.platform.name'))">
    <meta property="og:description" content="@yield('description', 'Portal informasi dan administrasi ' . ($school['name'] ?: 'sekolah') . '.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', $brandLogo)" />
    <meta name="twitter:card" content="summary_large_image">
    @php
        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            'name' => $school['name'] ?: config('branding.platform.name'),
            'url' => url('/'),
            'email' => $school['email'] ?: null,
            'telephone' => $school['phone'] ?: null,
            'address' => filled($school['address']) || filled($school['city']) ? [
                '@type' => 'PostalAddress',
                'streetAddress' => $school['address'] ?: null,
                'addressLocality' => $school['city'] ?: null,
                'addressRegion' => $school['province'] ?: null,
            ] : null,
        ];
        $structuredJson = json_encode($structuredData, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    @endphp
    <script type="application/ld+json">{{ $structuredJson }}</script>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $brandFavicon }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $brandAppIcon }}">
    <meta name="theme-color" content="{{ data_get($brand ?? [], 'primary') ?: config('branding.theme_color') }}">
    <script>
        (() => {
            const key = 'lyfla.theme';
            const stored = localStorage.getItem(key);
            const preference = ['light', 'dark', 'system'].includes(stored) ? stored : 'system';
            const effective = preference === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : preference;
            document.documentElement.dataset.themePreference = preference;
            document.documentElement.dataset.theme = effective;
            document.documentElement.style.colorScheme = effective;
        })();
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php
        $brandCustomCss = data_get($brand ?? [], 'tokens', [])['advanced.custom_css'] ?? null;
    @endphp
    @if (filled($brandCustomCss))
        <style data-brand-custom-css>{!! $brandCustomCss !!}</style>
    @endif
</head>
<body class="public-site h-full bg-[var(--app-bg)]">
<div class="min-h-full flex flex-col">
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<header x-data="{
    open: false,
    openMenu() { this.open = true; document.body.classList.add('overflow-hidden'); this.$nextTick(() => this.$refs.closeButton?.focus()); },
    closeMenu() { this.open = false; document.body.classList.remove('overflow-hidden'); this.$nextTick(() => this.$refs.menuButton?.focus()); },
}" class="sticky top-0 z-40 bg-[var(--app-sidebar-bg)] text-white">
    <div class="shell-wide">
        <div class="flex items-center justify-between gap-4 h-16">

            {{-- ============ Brand ============ --}}
            <a href="{{ route('home') }}" class="flex items-center gap-3 min-w-0 group">
                @if (filled(data_get($brand ?? [], 'logo')))
                    <x-brand.logo variant="lockup" height="h-9 w-auto max-w-[11rem] object-contain" alt="{{ $school['name'] ?: config('branding.platform.name') }}" />
                @else
                    <span class="grid place-items-center w-10 h-10 shrink-0 rounded-[var(--radius-lg)] bg-white">
                        <x-brand.logo variant="icon" height="h-8" alt="{{ config('branding.platform.name') }}" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-body font-bold leading-tight truncate group-hover:underline">
                            {{ $school['name'] ?: config('branding.platform.name') }}
                        </p>
                        <p class="text-[11px] text-white/55 leading-tight truncate hidden sm:block">{{ config('branding.platform.expansion') }}</p>
                    </div>
                @endif
            </a>

            {{-- ============ Desktop navigation ============ --}}
            {{--
                Hidden below `lg` and replaced by the drawer, because a seven-item
                horizontal row cannot fit a 768px tablet without either wrapping
                into two ragged lines or becoming a horizontal scroller — and a
                navigation that scrolls sideways hides items the visitor cannot
                see are there.

                `Route::has()` on every entry: a school running a build without
                one of these pages must not get a link to a 404 in its own header.
            --}}
            <nav aria-label="Navigasi utama" class="hidden lg:block">
                <ul class="flex items-center gap-1">
                    @foreach ([
                        ['route' => 'home',              'label' => 'Beranda'],
                        ['route' => 'public.about',      'label' => 'Profil'],
                        ['route' => 'public.programs',   'label' => 'Program'],
                        ['route' => 'public.news',       'label' => 'Berita'],
                        ['route' => 'public.contact',    'label' => 'Kontak'],
                    ] as $item)
                        @if (Route::has($item['route']))
                            @php $active = $item['route'] === 'home'
                                ? request()->routeIs('home')
                                : request()->routeIs($item['route'], $item['route'].'*'); @endphp

                            <li>
                                <a href="{{ route($item['route']) }}"
                                   @if ($active) aria-current="page" @endif
                                   class="relative block rounded-[var(--radius-sm)] px-3 py-2 text-small font-medium transition-colors
                                          {{ $active
                                                ? 'text-white'
                                                : 'text-white/70 hover:text-white hover:bg-white/10' }}">
                                    {{ $item['label'] }}

                                    {{-- The underline grows from the centre on
                                         hover and is a solid bar when current, so
                                         the active item is not marked by colour
                                         alone. --}}
                                    <span @class([
                                        'absolute inset-x-3 -bottom-px h-0.5 rounded-full transition-transform',
                                        'bg-[var(--brand-gold)]' => $active,
                                        'bg-white/50 origin-center scale-x-0 group-hover:scale-x-100' => ! $active,
                                    ])></span>
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </nav>

            {{-- ============ Actions ============ --}}
            <div class="flex shrink-0 items-center gap-2">
                <x-theme-switcher compact inverse />
                @if (Route::has('public.search'))
                    <a href="{{ route('public.search') }}"
                       class="grid size-11 place-items-center rounded-[var(--radius-md)] text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                       aria-label="Cari informasi sekolah" title="Cari informasi sekolah">
                        <x-icon name="search" class="size-5" />
                    </a>
                @endif

                {{-- The registration CTA is the dominant one for a public
                     visitor; the portal link is present but secondary. --}}
                @if (Route::has('login'))
                    <a href="{{ route('login') }}"
                       class="hidden sm:inline-flex items-center gap-2 rounded-[var(--radius-md)] px-3 py-2
                              text-small font-semibold text-white ring-1 ring-inset ring-white/30
                              transition-colors hover:bg-white/10 focus-visible:outline-2
                              focus-visible:outline-offset-2 focus-visible:outline-white">
                        <x-icon name="log-in" class="w-4 h-4" />
                        <span class="hidden xl:inline">Portal {{ config('branding.platform.name') }}</span>
                        <span class="xl:hidden">Portal</span>
                    </a>
                @endif

                @if (Route::has('public.admission'))
                    <a href="{{ route('public.admission') }}"
                       class="inline-flex items-center gap-2 rounded-[var(--radius-md)] bg-white px-4 py-2
                              text-small font-semibold text-[var(--app-primary)]
                              transition-colors hover:bg-white/90 focus-visible:outline-2
                              focus-visible:outline-offset-2 focus-visible:outline-white">
                        Daftar
                    </a>
                @endif

                {{-- Mobile: the drawer trigger. 44px tall, because a 40px target
                     is under the comfortable minimum on a phone. --}}
                <button type="button"
                        x-ref="menuButton"
                        @click="openMenu()"
                        :aria-expanded="open.toString()"
                        aria-controls="mobile-navigation"
                        class="lg:hidden grid place-items-center w-11 h-11 -mr-2 rounded-[var(--radius-md)]
                               text-white/80 hover:bg-white/10 transition-colors"
                        aria-label="Buka menu navigasi">
                    <x-icon name="menu" class="w-5 h-5" />
                </button>
            </div>
        </div>
    </div>

    {{-- ============ Mobile drawer ============ --}}
    {{--
        A full-height panel rather than a dropdown: the list runs to seven
        items plus two actions, and a dropdown that ends above the fold on a
        667px phone leaves the last items unreachable.
    --}}
    <div
         id="mobile-navigation"
         x-show="open" x-cloak
         x-bind:aria-hidden="(!open).toString()"
         @keydown.escape.window="closeMenu()"
         class="lg:hidden">

        <div x-show="open"
             x-transition.opacity.duration.200ms
             class="fixed inset-0 z-50 bg-black/60"
             @click="closeMenu()"
             aria-hidden="true"></div>

        <nav x-show="open"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-x-full"
             x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-x-0"
             x-transition:leave-end="translate-x-full"
             class="fixed inset-y-0 right-0 z-[60] flex w-[min(20rem,85vw)] flex-col
                    bg-[var(--app-sidebar-bg)] shadow-2xl"
             role="dialog"
             aria-modal="true"
             aria-label="Menu navigasi">

            <div class="flex items-center justify-between gap-3 h-16 px-4 border-b border-white/10 shrink-0">
                <span class="text-small font-semibold text-white">Menu</span>
                <button type="button" x-ref="closeButton" @click="closeMenu()"
                        class="grid place-items-center w-11 h-11 -mr-2 rounded-[var(--radius-md)]
                               text-white/80 hover:bg-white/10 transition-colors"
                        aria-label="Tutup menu">
                    <x-icon name="x" class="w-5 h-5" />
                </button>
            </div>

            <ul class="flex-1 overflow-y-auto p-3 space-y-1">
                @foreach ([
                    ['route' => 'home',            'label' => 'Beranda'],
                    ['route' => 'public.about',    'label' => 'Profil Sekolah'],
                    ['route' => 'public.programs', 'label' => 'Program'],
                    ['route' => 'public.admission','label' => 'PPDB'],
                    ['route' => 'public.news',     'label' => 'Berita'],
                    ['route' => 'public.contact',  'label' => 'Kontak'],
                ] as $item)
                    @if (Route::has($item['route']))
                        @php $active = $item['route'] === 'home'
                            ? request()->routeIs('home')
                            : request()->routeIs($item['route'], $item['route'].'*'); @endphp

                        <li>
                            <a href="{{ route($item['route']) }}"
                               @if ($active) aria-current="page" @endif
                               class="flex items-center min-h-11 rounded-[var(--radius-md)] px-3 text-body transition-colors
                                      {{ $active
                                            ? 'bg-white/15 text-white font-semibold'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                {{ $item['label'] }}
                                @if ($active)
                                    <x-icon name="check" class="w-4 h-4 ml-auto shrink-0 text-[var(--brand-gold)]" />
                                @endif
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>

            @if (Route::has('login') || Route::has('register'))
                <div class="p-3 border-t border-white/10 space-y-2 shrink-0">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="flex items-center justify-center min-h-11 rounded-[var(--radius-md)] bg-white
                                  text-body font-semibold text-[var(--app-primary)]">
                            Daftar Sekarang
                        </a>
                    @endif

                    @if (Route::has('login'))
                        <a href="{{ route('login') }}"
                           class="flex items-center justify-center min-h-11 rounded-[var(--radius-md)]
                                  text-body font-semibold text-white ring-1 ring-inset ring-white/30">
                            Portal {{ config('branding.platform.name') }}
                        </a>
                    @endif
                </div>
            @endif
        </nav>
    </div>
</header>

    <main id="main-content" class="flex-1">
        @yield('body')
    </main>

<footer class="mt-auto border-t border-[var(--app-border)] bg-[var(--app-surface)]">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 py-8">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-body font-bold text-[var(--app-text)]">{{ $school['name'] ?: config('branding.platform.name') }}</p>
                    @if (filled($school['address']) || filled($school['city']))
                        <p class="mt-1 text-caption text-[var(--app-text-muted)] max-w-sm">
                            {{ collect([$school['address'], $school['city'], $school['province']])->filter()->join(', ') }}
                        </p>
                    @endif
                </div>

                <nav aria-label="Halaman publik" class="flex flex-wrap gap-x-6 gap-y-2 text-caption">
                    <a href="{{ route('home') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('home'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('home')])>
                        Beranda
                    </a>
                    <a href="{{ route('public.about') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('public.about'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('public.about')])>
                        Profil
                    </a>
                    <a href="{{ route('public.news') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('public.news', 'public.news.*'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('public.news', 'public.news.*')])>
                        Berita
                    </a>
                    <a href="{{ route('public.programs') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('public.programs'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('public.programs')])>
                        Program
                    </a>
                    <a href="{{ route('public.admission') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('public.admission'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('public.admission')])>
                        PPDB
                    </a>
                    <a href="{{ route('public.contact') }}"
                       @class(['font-semibold text-[var(--app-primary)]' => request()->routeIs('public.contact'), 'text-[var(--app-text-muted)] hover:text-[var(--app-text)]' => ! request()->routeIs('public.contact')])>
                        Kontak
                    </a>
                </nav>
            </div>

            <p class="mt-6 pt-5 border-t border-[var(--app-border)] text-caption text-[var(--app-text-muted)]">
                © {{ now()->year }} {{ $school['name'] ?: config('branding.platform.name') }}
                @if (filled($school['npsn'])) · NPSN {{ $school['npsn'] }}@endif
            </p>
        </div>
    </footer>
</div>
</body>
</html>
