@extends('components.app-shell')

@section('title', 'Tambah Pengguna')
@section('page-title', 'Tambah Pengguna')
@section('page-description', 'Buat akun internal untuk staff sekolah')

@section('page-actions')
    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4 sm:space-y-5" novalidate
          x-data="{ saveOnly: false }">
        @csrf

        <x-card title="Data Akun" icon="user">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="name" label="Nama lengkap" required :value="old('name')"
                              autocomplete="off" />
                <x-form-field name="email" type="email" label="Email" required :value="old('email')"
                              autocomplete="off" />
                <x-form-field name="password" type="password" label="Password" required
                              autocomplete="new-password" hint="Minimal 8 karakter." />
                <x-form-field name="password_confirmation" type="password" label="Ulangi password" required
                              autocomplete="new-password" />
            </div>
        </x-card>

        <x-card title="Role & Status" icon="shield-check"
                description="Hanya role yang boleh Anda berikan yang tersedia di sini.">
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="role" class="label">Role <span class="text-[var(--app-danger)]">*</span></label>
                    <select id="role" name="role" class="field" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role }}" @selected(old('role') === $role)>
                                {{ ucfirst(str_replace('_', ' ', $role)) }}
                            </option>
                        @endforeach
                    </select>
                    <p class="help-text">
                        @if (in_array('super_admin', $roles, true))
                            Super Admin tersedia karena Anda berwenang menetapkannya.
                        @else
                            Super Admin tidak ditampilkan — hanya Super Admin yang dapat menetapkannya.
                        @endif
                    </p>
                </div>

                <div>
                    <span class="label">Status Akun</span>
                    <label class="flex items-center gap-2.5 h-10 text-body cursor-pointer select-none">
                        <input type="checkbox" name="is_active" value="1" checked
                               class="checkbox-field rounded">
                        Aktif dan dapat masuk
                    </label>
                    <p class="help-text">Nonaktif berarti tidak dapat login, tetapi riwayat tetap tersimpan.</p>
                </div>
            </div>
        </x-card>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
            <a href="{{ route('admin.users') }}" class="btn btn-secondary justify-center">Batal</a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-icon name="user-plus" class="w-4 h-4" />
                Buat Pengguna
            </button>
        </div>
    </form>
</div>
@endsection
