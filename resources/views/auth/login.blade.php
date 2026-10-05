<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Masuk · {{ config('branding.platform.name') }}</title>
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/apple-touch-icon.png') }}">
    <meta name="theme-color" content="{{ config('branding.theme_color') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[var(--app-bg)]">
<div class="min-h-full grid lg:grid-cols-2">

    {{-- Brand panel: hidden on phones where it would just push the form down --}}
    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-14 bg-[var(--app-sidebar-bg)] relative overflow-hidden">
        <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
             style="background-image:radial-gradient(circle at 22% 18%, white 0, transparent 42%),radial-gradient(circle at 78% 82%, white 0, transparent 46%)"></div>

        {{-- The official LYFLA mark. It carries its own colour, so it sits on the
             dark panel directly rather than inside a tinted chip, which would
             double the contrast and muddy the mark. --}}
        <div class="relative flex items-center gap-3">
            {{-- Same contrast reasoning as the sidebar: the emblem's navy needs a
                 light plate to read against the navy panel. --}}
            <span class="grid place-items-center w-12 h-12 shrink-0 rounded-[var(--radius-lg)] bg-white">
                <x-brand.logo variant="icon" height="h-10" :alt="config('branding.platform.name')" />
            </span>
            <div>
                <p class="text-body font-bold text-white leading-tight">{{ config('branding.platform.name') }}</p>
                <p class="text-[11px] text-white/50 leading-tight">Sistem Informasi Data Siswa</p>
            </div>
        </div>

        <div class="relative max-w-md">
            <h2 class="font-[var(--font-display)] text-4xl font-extrabold text-white leading-[1.15] tracking-tight">
                Pendaftaran siswa,<br>dokumen, dan verifikasi<br> dalam satu tempat.
            </h2>
            <p class="mt-4 text-body text-white/60 leading-relaxed">
                Kelola data siswa dari Registrasi hingga Terverifikasi, lengkap dengan
                pelacakan berkas dan riwayat verifikasi yang dapat diaudit.
            </p>

            <ul class="mt-8 space-y-3">
                @foreach ([
                    ['clipboard-check', 'Alur pendaftaran bertahap', 'Biodata, orang tua, pendidikan, dokumen.'],
                    ['shield-check', 'Verifikasi dengan catatan', 'Setiap penolakan disertai alasan yang jelas.'],
                    ['chart-bar', 'Laporan siap cetak', 'Excel, CSV, dan PDF berdasarkan filter.'],
                ] as [$icon, $title, $desc])
                    <li class="flex items-start gap-3">
                        <span class="shrink-0 grid place-items-center w-8 h-8 rounded-[var(--radius-md)] bg-white/10">
                            <x-icon :name="$icon" class="w-4 h-4 text-white" />
                        </span>
                        <div>
                            <p class="text-small font-semibold text-white">{{ $title }}</p>
                            <p class="text-caption text-white/50">{{ $desc }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        <p class="relative text-caption text-white/35">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </div>

    {{-- Form panel --}}
    <div class="flex flex-col justify-center px-5 py-10 sm:px-8 lg:px-12">
        <div class="w-full max-w-sm mx-auto">

            <div class="lg:hidden flex items-center gap-2.5 mb-8">
                <span class="grid place-items-center w-9 h-9 rounded-[var(--radius-md)] bg-brand-700">
                    <x-icon name="graduation-cap" class="w-5 h-5 text-white" />
                </span>
                <div>
                    <p class="text-body font-bold text-[var(--app-text)] leading-tight">{{ config('branding.platform.name') }}</p>
                    <p class="text-[11px] text-[var(--app-text-muted)] leading-tight">Sistem Informasi Data Siswa</p>
                </div>
            </div>

            <h1 class="text-h1 font-bold text-[var(--app-text)]">Masuk ke akun Anda</h1>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">Gunakan akun yang diberikan sekolah.</p>

            @if (session('success'))
                <x-alert variant="success" :message="session('success')" class="mt-5" />
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" novalidate>
                @csrf

                <x-form-field
                    name="email" type="email" label="Email" required
                    placeholder="nama@sekolah.sch.id" autocomplete="email" />

                <div x-data="{ show: false }">
                    <label class="label" for="password">Password</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'"
                               required autocomplete="current-password"
                               class="field pr-11 @error('password') field-error @enderror"
                               placeholder="Masukkan password">
                        <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 grid place-items-center w-11 text-[var(--app-text-subtle)] hover:text-[var(--app-text-muted)]"
                                :aria-label="show ? 'Sembunyikan password' : 'Tampilkan password'">
                            <x-icon name="eye" class="w-[18px] h-[18px]" x-show="!show" />
                            <x-icon name="eye-off" class="w-[18px] h-[18px]" x-show="show" x-cloak />
                        </button>
                    </div>
                    @error('password')
                        <p class="error-text flex items-start gap-1">
                            <x-icon name="alert-circle" class="w-3.5 h-3.5 shrink-0 mt-px" />
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <label class="flex items-center gap-2.5 text-small text-[var(--app-text-muted)] cursor-pointer select-none">
                    <input type="checkbox" name="remember" value="1" class="checkbox-field rounded">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="btn btn-primary btn-lg w-full" data-loading>
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-body text-[var(--app-text-muted)]">
                Belum punya akun?
                <a href="{{ route('register') }}" class="font-semibold text-[var(--app-primary)] hover:underline">Daftar sekarang</a>
            </p>

        </div>
    </div>
</div>
</body>
</html>
