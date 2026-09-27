@extends('components.app-shell')

@section('title', 'Ubah '.$user->name)
@section('page-title', 'Ubah Pengguna')
@section('page-description', $user->email)

@section('page-actions')
    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-2xl">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @method('PUT')

        <x-card title="Data Akun" icon="user">
            <div class="grid sm:grid-cols-2 gap-4">
                <x-form-field name="name" label="Nama lengkap" required :value="old('name', $user->name)" />
                <x-form-field name="email" type="email" label="Email" required
                              :value="old('email', $user->email)" />
                <x-form-field name="phone" label="Nomor HP (opsional)" :value="old('phone', $user->phone)" />
            </div>
        </x-card>

        <x-alert variant="info" title="Role diubah terpisah"
                 message="Untuk menghindari kesalahan, role dikelola di halaman detail pengguna." />

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-secondary justify-center">Batal</a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-icon name="save" class="w-4 h-4" />
                Simpan Perubahan
            </button>
        </div>
    </form>
</div>
@endsection
