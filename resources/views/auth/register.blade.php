@extends('auth.layout')

@section('title', 'Daftar')

@section('auth-eyebrow')
    <span class="auth-eyebrow">Pendaftaran siswa</span>
@endsection

@section('auth-visual-title')
    Mulai dari data yang benar.
@endsection

@section('auth-visual-description')
    Lengkapi informasi secara bertahap. Tim sekolah akan meninjau data dan dokumen sebelum akun Anda diverifikasi.
@endsection

@section('auth-visual-points')
    @foreach ([
        ['user', 'Data pribadi', 'Nama, email, NISN, dan password untuk akun Anda.'],
        ['users', 'Data wali', 'Informasi orang tua atau wali untuk kebutuhan sekolah.'],
        ['file-check', 'Dokumen pendukung', 'Dokumen dapat dilengkapi dan ditinjau pada tahap berikutnya.'],
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
    <a href="{{ route('login') }}" class="auth-top-link">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali ke halaman masuk
    </a>
@endsection

@section('auth-content')
    <p class="auth-form-kicker">Langkah pertama</p>
    <h2 class="auth-title">Buat akun siswa</h2>
    <p class="auth-description">Pendaftaran untuk tahun ajaran {{ $year?->name ?? date('Y').'/'.(date('Y') + 1) }}.</p>

    @if ($errors->any())
        <x-alert variant="error" title="Periksa kembali isian Anda"
                 class="mt-5" :message="'Ada '.$errors->count().' isian yang perlu diperbaiki.'" />
    @endif

    <form method="POST" action="{{ route('register') }}" class="auth-form" novalidate>
        @csrf

        <div class="auth-form-section">
            <div class="auth-form-section-heading">
                <span class="auth-form-section-icon"><x-icon name="user" class="w-4 h-4" /></span>
                <div>
                    <h3>Data akun</h3>
                    <p>Gunakan data yang sesuai dokumen resmi.</p>
                </div>
            </div>

            <div class="auth-form-grid">
                <x-form-field name="name" label="Nama lengkap" required autocomplete="name"
                              :value="old('name')" placeholder="Sesuai akta kelahiran" />
                <x-form-field name="email" type="email" label="Email aktif" required autocomplete="email"
                              :value="old('email')" placeholder="nama@sekolah.sch.id"
                              hint="Dipakai untuk masuk ke akun Anda." />
            </div>

            <x-form-field name="nisn" label="NISN" required :value="old('nisn')"
                          placeholder="10 digit angka" inputmode="numeric"
                          hint="Nomor Induk Siswa Nasional dari sekolah asal." />

            <div class="auth-form-grid">
                <x-form-field name="password" type="password" label="Password" required
                              autocomplete="new-password" hint="Minimal 8 karakter." />
                <x-form-field name="password_confirmation" type="password" label="Ulangi password" required
                              autocomplete="new-password" />
            </div>
        </div>

        <label class="auth-checkbox-label auth-terms-label">
            <input type="checkbox" name="terms" value="1" required
                   class="checkbox-field rounded @error('terms') !border-[var(--app-danger)] @enderror">
            <span>Saya menyatakan data yang diisi benar dan dapat dipertanggungjawabkan.</span>
        </label>
        @error('terms')
            <p class="error-text -mt-2">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn btn-primary btn-lg auth-submit">Buat akun dan lanjutkan</button>
    </form>
@endsection

@section('auth-switch')
    <span>Sudah punya akun?</span>
    <a href="{{ route('login') }}">Masuk di sini</a>
@endsection