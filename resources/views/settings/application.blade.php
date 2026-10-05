@extends('components.app-shell')

@section('title', 'Pengaturan Aplikasi')
@section('page-title', 'Aplikasi')
@section('page-description', 'Preferensi tampilan dan format')

@section('page-actions')
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-3xl">
    <form method="POST" action="{{ route('settings.application.update') }}" class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @method('PUT')

        {{--
            The application's own name. It appears in the browser title, the
            PWA manifest and the login page, and until this field existed the
            only way to change it was to edit the settings table by hand.
        --}}
        <x-card title="Identitas Aplikasi" icon="school">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="app.name" label="Nama Aplikasi" required
                              :value="old('app.name', $values['app.name']['value'] ?? 'Sistem Informasi Data Siswa')"
                              hint="Tampil di judul browser, manifest, dan halaman masuk." />
                <x-form-field name="app.short_name" label="Nama Pendek" required maxlength="60"
                              :value="old('app.short_name', $values['app.short_name']['value'] ?? config('branding.platform.name'))"
                              hint="Dipakai pada menu sisi dan nama ringkas." />
                <x-form-field name="app.tagline" class="sm:col-span-2"
                              :value="old('app.tagline', $values['app.tagline']['value'] ?? null)"
                              placeholder="Contoh: Pendaftaran, akademik, dan informasi sekolah." />
            </div>
        </x-card>

        <x-card title="Format & Regional" icon="settings">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="app.timezone" type="select" label="Zona Waktu" required
                              :value="old('app.timezone', $values['app.timezone']['value'] ?? 'Asia/Jakarta')">
                    @foreach (['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'] as $tz)
                        <option value="{{ $tz }}">{{ $tz }}</option>
                    @endforeach
                </x-form-field>
                <x-form-field name="app.date_format" label="Format Tanggal" required
                              :value="old('app.date_format', $values['app.date_format']['value'] ?? 'd M Y')"
                              hint="Contoh: d M Y" />
            </div>
        </x-card>

        <x-card title="Tampilan Daftar" icon="list-checks">
            <x-form-field name="app.per_page" type="number" label="Jumlah Baris per Halaman" required
                          min="5" max="100"
                          :value="old('app.per_page', $values['app.per_page']['value'] ?? 15)"
                          hint="Antara 5 sampai 100." />
        </x-card>

        <x-alert variant="info" title="Konfigurasi sensitif"
                 message="APP_KEY, kredensial database, dan variabel lingkungan tidak dapat diubah dari sini. Nilai tersebut dikelola di luar aplikasi demi keamanan." />

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <x-icon name="save" class="w-4 h-4" />
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
