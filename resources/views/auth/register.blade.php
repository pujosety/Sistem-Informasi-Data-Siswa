<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Daftar — {{ config('app.name') }}</title>
    <link rel="icon" href="/favicon.ico" sizes="any">
    <meta name="theme-color" content="#1e2f6b">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[var(--app-bg)]">
<div class="min-h-full grid lg:grid-cols-2">

    <div class="hidden lg:flex flex-col justify-between p-10 xl:p-14 bg-[var(--app-sidebar-bg)] relative overflow-hidden">
        <div class="absolute inset-0 opacity-[0.07] pointer-events-none"
             style="background-image:radial-gradient(circle at 26% 22%, white 0, transparent 44%),radial-gradient(circle at 74% 80%, white 0, transparent 46%)"></div>

        <div class="relative flex items-center gap-2.5">
            <span class="grid place-items-center w-9 h-9 rounded-[var(--radius-md)] bg-white/12">
                <x-icon name="user-plus" class="w-5 h-5 text-white" />
            </span>
            <div>
                <p class="text-body font-bold text-white leading-tight">Pendaftaran Siswa</p>
                <p class="text-[11px] text-white/50 leading-tight">{{ date('Y') }}/{{ date('Y') + 1 }}</p>
            </div>
        </div>

        <div class="relative max-w-md">
            <h2 class="font-[var(--font-display)] text-4xl font-extrabold text-white leading-[1.15] tracking-tight">
                Lima langkah sederhana<br>menuju siswa terverifikasi.
            </h2>

            <ol class="mt-8 space-y-4">
                @foreach ([
                    'Isi data pribadi Anda',
                    'Lengkapi data orang tua atau wali',
                    'Masukkan riwayat pendidikan',
                    'Unggah dokumen pendukung',
                    'Tinjau lalu kirim untuk diverifikasi',
                ] as $i => $line)
                    <li class="flex items-start gap-3">
                        <span class="shrink-0 grid place-items-center w-6 h-6 rounded-full bg-white/12 text-white text-caption font-bold">
                            {{ $i + 1 }}
                        </span>
                        <p class="text-small text-white/75 pt-0.5">{{ $line }}</p>
                    </li>
                @endforeach
            </ol>
        </div>

        <p class="relative text-caption text-white/35">Data Anda hanya dapat diakses oleh admin dan petugas kesiswaan.</p>
    </div>

    <div class="flex flex-col justify-center px-5 py-10 sm:px-8 lg:px-12">
        <div class="w-full max-w-md mx-auto">

            <div class="lg:hidden flex items-center gap-2.5 mb-8">
                <span class="grid place-items-center w-9 h-9 rounded-[var(--radius-md)] bg-brand-700">
                    <x-icon name="user-plus" class="w-5 h-5 text-white" />
                </span>
                <p class="text-body font-bold text-[var(--app-text)]">Pendaftaran Siswa</p>
            </div>

            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 text-small text-[var(--app-text-muted)] hover:text-[var(--app-text)] transition-colors">
                <x-icon name="arrow-left" class="w-4 h-4" />
                Kembali ke halaman masuk
            </a>

            <h1 class="mt-4 text-h1 font-bold text-[var(--app-text)]">Buat akun siswa</h1>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Pendaftaran untuk tahun ajaran {{ $year?->name ?? date('Y').'/'.(date('Y') + 1) }}.
            </p>

            @if ($errors->any())
                <x-alert variant="error" title="Periksa kembali isian Anda"
                         class="mt-5" message="Ada '.$errors->count().' isian yang perlu diperbaiki." />
            @endif

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4" novalidate>
                @csrf

                <div class="surface p-4 sm:p-5 space-y-4">
                    <h2 class="text-h3 font-semibold text-[var(--app-text)]">Data akun</h2>

                    <x-form-field name="name" label="Nama lengkap" required autocomplete="name"
                                  :value="old('name')" placeholder="Sesuai akta kelahiran" />
                    <x-form-field name="email" type="email" label="Email aktif" required autocomplete="email"
                                  :value="old('email')" placeholder="nama@sekolah.sch.id"
                                  hint="Dipakai untuk masuk ke akun Anda." />
                    <x-form-field name="nisn" label="NISN" required :value="old('nisn')"
                                  placeholder="10 digit angka" inputmode="numeric"
                                  hint="Nomor Induk Siswa Nasional dari sekolah asal." />
                    <x-form-field name="password" type="password" label="Password" required
                                  autocomplete="new-password" hint="Minimal 8 karakter." />
                    <x-form-field name="password_confirmation" type="password" label="Ulangi password" required
                                  autocomplete="new-password" />
                </div>

                <label class="flex items-start gap-2.5 text-small text-[var(--app-text-muted)] cursor-pointer">
                    <input type="checkbox" name="terms" value="1" required
                           class="checkbox-field rounded mt-0.5 @error('terms') !border-[var(--app-danger)] @enderror">
                    <span>Saya menyatakan data yang diisi benar dan dapat dipertanggungjawabkan.</span>
                </label>
                @error('terms')
                    <p class="error-text -mt-2">{{ $message }}</p>
                @enderror

                <button type="submit" class="btn btn-primary btn-lg w-full">Buat akun &amp; lanjutkan</button>
            </form>

            <p class="mt-6 text-center text-body text-[var(--app-text-muted)]">
                Sudah punya akun?
                <a href="{{ route('login') }}" class="font-semibold text-[var(--app-primary)] hover:underline">Masuk di sini</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
