@extends('components.app-shell')

@section('title', 'Role & Hak Akses')
@section('page-title', 'Role & Hak Akses')
@section('page-description', 'Atur izin setiap role di aplikasi')

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach ($roles as $role)
        @php $isSuper = $role->name === 'super_admin'; @endphp
        <x-card class="flex flex-col">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="min-w-0">
                    <h2 class="text-h3 font-bold text-[var(--app-text)]">
                        {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                    </h2>
                    <p class="text-caption text-[var(--app-text-muted)] mt-0.5">
                        {{ $role->users_count }} pengguna · {{ $role->permissions->count() }} izin
                    </p>
                </div>
                @if ($isSuper)
                    <span class="badge badge-brand shrink-0">
                        <x-icon name="lock" class="w-3 h-3" />
                        Protected
                    </span>
                @endif
            </div>

            @if ($isSuper)
                <p class="text-small text-[var(--app-text-muted)]">
                    Memiliki seluruh izin secara otomatis, termasuk izin yang ditambahkan di kemudian hari.
                </p>
            @else
                <div class="flex flex-wrap gap-1 mb-4">
                    @foreach ($role->permissions->take(4) as $p)
                        <span class="badge badge-neutral">{{ explode('.', $p->name)[0] }}</span>
                    @endforeach
                    @if ($role->permissions->count() > 4)
                        <span class="badge badge-neutral">+{{ $role->permissions->count() - 4 }}</span>
                    @endif
                    @if ($role->permissions->isEmpty())
                        <span class="text-caption text-[var(--app-text-subtle)]">Belum ada izin</span>
                    @endif
                </div>

                @can('role.update')
                    <a href="{{ route('admin.roles.edit', $role) }}" class="btn btn-primary w-full justify-center mt-auto">
                        <x-icon name="settings" class="w-4 h-4" />
                        Kelola Izin
                    </a>
                @endcan
            @endif
        </x-card>
    @endforeach
</div>

<x-alert variant="info" class="mt-5"
         title="Perlindungan hak akses"
         message="Anda hanya dapat memberikan izin yang Anda miliki sendiri. Role Super Admin, Admin, dan Siswa dilindungi dari penghapusan." />
@endsection
