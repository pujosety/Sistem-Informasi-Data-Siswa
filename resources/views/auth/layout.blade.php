@php
    $runtimeBrandCss = app(\App\Services\BrandService::class)->cssVariables(
        data_get($brand ?? [], 'primary'),
        data_get($brand ?? [], 'accent'),
        data_get($brand ?? [], 'tokens', []),
    );
    $brandName = data_get($brand ?? [], 'name') ?: config('branding.platform.name');
    $brandShortName = data_get($brand ?? [], 'shortName') ?: config('branding.platform.name');
    $brandTagline = data_get($brand ?? [], 'tagline') ?: 'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.';
    $authLogo = data_get($brand ?? [], 'assets.loginLogo')
        ?: data_get($brand ?? [], 'icon')
        ?: data_get($brand ?? [], 'logo');
    $brandFavicon = data_get($brand ?? [], 'assets.favicon') ?: asset('branding/favicon-32x32.png');
    $brandAppIcon = data_get($brand ?? [], 'assets.appIcon') ?: asset('branding/apple-touch-icon.png');
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full" style="{{ $runtimeBrandCss }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ data_get($brand ?? [], 'primary') ?: config('branding.theme_color') }}">
    <title>@yield('title') · {{ $brandShortName }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="{{ $brandFavicon }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ $brandAppIcon }}">
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
<body class="auth-page">
    <a class="auth-skip" href="#auth-content">Lewati ke formulir</a>

    <div class="auth-shell">
        <div class="auth-frame">
            <aside class="auth-visual" aria-label="Informasi {{ $brandShortName }}">
                <div class="auth-visual-orbit auth-visual-orbit-one" aria-hidden="true"></div>
                <div class="auth-visual-orbit auth-visual-orbit-two" aria-hidden="true"></div>

                <div class="auth-visual-top">
                    <div class="auth-logo-plate">
                        <x-brand.logo variant="lockup" height="h-12 w-auto max-w-full object-contain" :alt="$brandShortName" :src="$authLogo" />
                    </div>
                    <div class="min-w-0">
                        <p class="auth-brand-name">{{ $brandShortName }}</p>
                        <p class="auth-brand-subtitle">{{ $brandName }}</p>
                    </div>
                </div>

                <div class="auth-visual-copy">
                    @yield('auth-eyebrow')
                    <h1>@yield('auth-visual-title')</h1>
                    <p>@yield('auth-visual-description')</p>
                    <div class="auth-visual-points">
                        @yield('auth-visual-points')
                    </div>
                </div>

                <div class="auth-visual-footer">
                    <span>{{ $brandTagline }}</span>
                    <span>&copy; {{ date('Y') }} {{ $brandShortName }}</span>
                </div>
            </aside>

            <main class="auth-main" id="auth-content">
                <div class="auth-mobile-brand">
                    <div class="auth-logo-plate auth-logo-plate-small">
                        <x-brand.logo variant="lockup" height="h-9 w-auto max-w-[9rem] object-contain" :alt="$brandShortName" :src="$authLogo" />
                    </div>
                    <div>
                        <p class="auth-brand-name">{{ $brandShortName }}</p>
                        <p class="auth-brand-subtitle">{{ $brandTagline }}</p>
                    </div>
                </div>

                <div class="auth-card">
                    <div class="auth-card-top">
                        @yield('auth-top-link')
                    </div>

                    <div class="auth-form-wrap">
                        @yield('auth-content')
                    </div>

                    <div class="auth-switch">
                        @yield('auth-switch')
                    </div>
                </div>

                <p class="auth-main-footer">Akses aman untuk siswa, guru, orang tua, dan pengelola sekolah.</p>
            </main>
        </div>
    </div>
</body>
</html>
