@php
    // One polished template for every HTTP error. Never leak internals:
    // in production we only ever show the safe copy below.
    $status = (int) ($status ?? (method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500));
    $isProd = ! config('app.debug');

    $map = [
        401 => ['icon' => 'lock', 'title' => 'Sesi berakhir', 'desc' => 'Silakan masuk kembali untuk melanjutkan.'],
        403 => ['icon' => 'lock', 'title' => 'Akses ditolak', 'desc' => 'Halaman ini tidak tersedia untuk akun Anda.'],
        404 => ['icon' => 'file-search', 'title' => 'Halaman tidak ditemukan', 'desc' => 'Alamat yang Anda tuju mungkin sudah berubah atau tidak pernah ada.'],
        419 => ['icon' => 'clock', 'title' => 'Sesi kedaluwarsa', 'desc' => 'Halaman sudah terlalu lama terbuka. Muat ulang dan coba lagi.'],
        422 => ['icon' => 'alert-triangle', 'title' => 'Data tidak valid', 'desc' => 'Periksa kembali isian yang ditandai.'],
        429 => ['icon' => 'clock', 'title' => 'Terlalu banyak permintaan', 'desc' => 'Tunggu sebentar sebelum mencoba lagi.'],
        500 => ['icon' => 'alert-circle', 'title' => 'Terjadi kesalahan', 'desc' => 'Ada gangguan di sisi kami. Tim teknis sudah diberi tahu.'],
        503 => ['icon' => 'loader', 'title' => 'Layanan sedang dalam pemeliharaan', 'desc' => 'Sistem sedang diperbarui. Coba lagi sebentar lagi.'],
    ];

    $e = $map[$status] ?? ['icon' => 'alert-circle', 'title' => 'Terjadi kesalahan', 'desc' => 'Permintaan Anda tidak dapat diproses.'];

    /*
     | When someone lands on a 403 (or a 404) by typing or following a stale
     | link, a dead end is worse than the error itself. Offer the destinations
     | their own account can actually reach, so nobody is left guessing.
     */
    $user = auth()->user();
    $destinations = [];

    /*
     | The suggested-destinations list comes from NavigationService, which reads
     | the roles/permissions tables. When the database is unreachable — the
     | exact situation this page exists to explain — that query throws, and a
     | second failure inside the error renderer replaces a useful message with
     | a blank screen. Suggesting nothing is strictly better than crashing.
     */
    if ($user) {
        $nav = [];

        try {
            $nav = app(\App\Services\NavigationService::class)->forUser($user)['items'];
        } catch (\Throwable $e) {
            report($e);
        }

        foreach ($nav as $item) {
            if (! empty($item['route'])) {
                $destinations[] = ['label' => $item['label'], 'url' => route($item['route'])];
            }

            foreach ($item['children'] ?? [] as $child) {
                $destinations[] = ['label' => $child['label'], 'url' => route($child['route'])];
            }
        }
    }
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $status }} — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[var(--app-bg)]">
<div class="min-h-full flex flex-col items-center justify-center px-5 py-14 text-center">
    <span class="grid place-items-center w-14 h-14 rounded-[var(--radius-xl)] bg-[var(--app-danger-soft)] text-[var(--app-danger)] mb-5">
        <x-icon name="{{ $e['icon'] }}" class="w-7 h-7" />
    </span>

    <p class="font-[var(--font-display)] text-[60px] leading-none font-extrabold text-ink-200">{{ $status }}</p>
    <h1 class="mt-3 text-h1 font-bold text-[var(--app-text)]">{{ $e['title'] }}</h1>
    <p class="mt-2 text-body text-[var(--app-text-muted)] max-w-md">{{ $e['desc'] }}</p>

    @unless ($isProd)
        @if ($exception->getMessage() && $exception->getMessage() !== $e['title'])
            <div class="mt-6 max-w-2xl w-full text-left surface p-4">
                <p class="text-caption font-semibold text-[var(--app-danger)] mb-1">
                    Detail (hanya tampil saat APP_DEBUG=true)
                </p>
                <p class="text-caption font-mono text-[var(--app-text-muted)] break-words">{{ $exception->getMessage() }}</p>
            </div>
        @endif
    @endunless

    @if ($destinations)
        <div class="mt-8 w-full max-w-lg">
            <p class="text-small font-semibold text-[var(--app-text)] mb-3">
                @if ($status === 403)
                    Halaman yang bisa Anda buka:
                @else
                    Mungkin Anda mencari:
                @endif
            </p>
            <div class="flex flex-wrap justify-center gap-2">
                @foreach ($destinations as $i => $destination)
                    <a href="{{ $destination['url'] }}"
                       class="btn {{ $i === 0 ? 'btn-primary' : 'btn-secondary' }} justify-center">
                        {{ $destination['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 flex flex-col sm:flex-row gap-2">
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('login') }}"
           class="btn btn-ghost justify-center">
            <x-icon name="arrow-left" class="w-4 h-4" />
            Kembali
        </a>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="btn btn-primary justify-center">
            <x-icon name="home" class="w-4 h-4" />
            {{ auth()->check() ? 'Ke dashboard' : 'Halaman masuk' }}
        </a>
    </div>
</div>
</body>
</html>
