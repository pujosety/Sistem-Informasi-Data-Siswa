@extends('auth.layout')

@section('title', 'Masuk')

@section('auth-eyebrow')
    <span class="auth-eyebrow">Portal LYFLA</span>
@endsection

@section('auth-visual-title')
    Semua urusan sekolah, lebih terarah.
@endsection

@section('auth-visual-description')
    Masuk ke portal untuk mengelola pendaftaran, data siswa, pembelajaran, dan informasi sekolah dalam satu ruang yang rapi.
@endsection

@section('auth-visual-points')
    @foreach ([
        ['shield-check', 'Akses sesuai peran', 'Setiap akun melihat ruang kerja yang sesuai tanggung jawabnya.'],
        ['folder-open', 'Data tetap tertata', 'Riwayat, dokumen, dan proses sekolah tersimpan dalam alur yang jelas.'],
        ['sparkles', 'Pengalaman yang tenang', 'Antarmuka ringkas agar pekerjaan sekolah terasa lebih ringan.'],
    ] as [$icon, $title, $description])
        <div class="auth-point">
            <span class="auth-point-icon"><x-icon :name="$icon" class="w-4 h-4" /></span>
            <div>
                <p class="auth-point-title">{{ $title }}</p>
                <p class="auth-point-description">{{ $description }}</p>
            </div>
        </div>
    @endforeach
@endsection

@section('auth-top-link')
    <a href="{{ route('home') }}" class="auth-top-link">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Situs sekolah
    </a>
@endsection

@section('auth-content')
    <p class="auth-form-kicker">Selamat datang kembali</p>
    <h2 class="auth-title">Masuk ke akun Anda</h2>
    <p class="auth-description">Gunakan akun yang diberikan sekolah untuk melanjutkan.</p>

    @if (session('success'))
        <x-alert variant="success" :message="session('success')" class="mt-5" />
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form" novalidate>
        @csrf

        <x-form-field
            name="email" type="email" label="Email" required
            placeholder="nama@sekolah.sch.id" autocomplete="email" />

        <div class="auth-field-group" x-data="{ show: false }">
            <label class="auth-label" for="password">Password</label>
            <div class="auth-password-wrap">
                <input id="password" name="password" :type="show ? 'text' : 'password'"
                       required autocomplete="current-password"
                       class="field auth-field pr-12 @error('password') field-error @enderror"
                       placeholder="Masukkan password">
                <button type="button" @click="show = !show"
                        class="auth-password-toggle"
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

        <div class="auth-form-row">
            <label class="auth-checkbox-label">
                <input type="checkbox" name="remember" value="1" class="checkbox-field rounded">
                <span>Ingat saya</span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary btn-lg auth-submit" data-loading>
            Masuk ke portal
        </button>
    </form>
@endsection

@section('auth-switch')
    <span>Belum punya akun siswa?</span>
    <a href="{{ route('register') }}">Daftar sekarang</a>
@endsection