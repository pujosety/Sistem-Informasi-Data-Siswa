@extends('components.app-shell')

@section('title', 'Pengaturan Pendaftaran')
@section('page-title', 'Pendaftaran')
@section('page-description', 'Periode dan status pendaftaran siswa baru')

@section('page-actions')
    <a href="{{ route('settings.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-3xl">
    <form method="POST" action="{{ route('settings.registration.update') }}" class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @method('PUT')

        <x-card title="Status Pendaftaran" icon="clipboard-check"
                description="Bila ditutup, halaman daftar menampilkan informasi bahwa pendaftaran sedang ditutup.">
            <label class="flex items-start gap-3 p-4 rounded-[var(--radius-md)] border border-[var(--app-border)] cursor-pointer
                          hover:bg-[var(--app-surface-muted)] transition-colors">
                <input type="checkbox" name="registration.open" value="1"
                       @checked(old('registration.open', $values['registration.open']['value'] ?? true))
                       class="checkbox-field rounded mt-0.5">
                <span>
                    <span class="block text-body font-semibold text-[var(--app-text)]">Pendaftaran dibuka</span>
                    <span class="block text-small text-[var(--app-text-muted)]">
                        Siswa baru dapat mendaftar dan mengirim berkas.
                    </span>
                </span>
            </label>
        </x-card>

        <x-card title="Periode" icon="calendar">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="registration.start_at" type="date" label="Mulai Pendaftaran"
                              :value="old('registration.start_at', $values['registration.start_at']['value'] ?? '')" />
                <x-form-field name="registration.end_at" type="date" label="Akhir Pendaftaran"
                              :value="old('registration.end_at', $values['registration.end_at']['value'] ?? '')" />
            </div>
            <p class="help-text mt-3">
                Periode bersifat informatif — status buka/tutup di atas yang benar-benar mengendalikan akses.
            </p>
        </x-card>

        <x-card title="Tahun Ajaran" icon="book">
            <x-form-field name="registration.default_academic_year_id" type="select" label="Tahun Ajaran Default"
                          placeholder="— Gunakan tahun ajaran aktif —"
                          :value="old('registration.default_academic_year_id', $values['registration.default_academic_year_id']['value'] ?? '')">
                @foreach ($years as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-form-field>
        </x-card>

        <div class="flex justify-end">
            <button type="submit" class="btn btn-primary">
                <x-icon name="save" class="w-4 h-4" />
                Simpan Pengaturan
            </button>
        </div>
    </form>
</div>
@endsection
