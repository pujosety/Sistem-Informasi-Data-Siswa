@extends('components.app-shell')

@section('title', $user->name)
@section('page-title', $user->name)
@section('page-description', $user->email)

@section('page-actions')
    <a href="{{ route('admin.users') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="grid lg:grid-cols-3 gap-4 sm:gap-5">
    <div class="lg:col-span-2 space-y-4 sm:space-y-5">

        {{-- ---------- Identity ---------- --}}
        <x-card title="Identitas" icon="user">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <span @class([
                    'shrink-0 grid place-items-center w-16 h-16 rounded-full text-h2 font-bold',
                    'bg-brand-50 text-brand-700' => $user->isActive(),
                    'bg-ink-100 text-ink-500' => ! $user->isActive(),
                ])>{{ strtoupper(mb_substr($user->name, 0, 2)) }}</span>
                <div class="min-w-0">
                    <h2 class="text-h2 font-bold text-[var(--app-text)]">{{ $user->name }}</h2>
                    <p class="text-body text-[var(--app-text-muted)] break-all">{{ $user->email }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span @class([
                            'badge',
                            'badge-success' => $user->isActive(),
                            'badge-danger'  => ! $user->isActive(),
                        ])>
                            <x-icon :name="$user->isActive() ? 'check-circle' : 'x'" class="w-3 h-3" />
                            {{ $user->isActive() ? 'Aktif' : 'Nonaktif' }}
                        </span>
                        @if ($user->isLastSuperAdmin())
                            <span class="badge badge-warning">
                                <x-icon name="lock" class="w-3 h-3" />
                                Super Admin terakhir
                            </span>
                        @endif
                    </div>
                    @if (! $user->isActive() && $user->disabled_reason)
                        <p class="mt-2 text-caption text-[var(--app-danger)]">Alasan: {{ $user->disabled_reason }}</p>
                    @endif
                </div>
            </div>

            <dl class="mt-5 pt-5 border-t border-[var(--app-border)] grid sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                <div><dt class="text-caption text-[var(--app-text-muted)]">Login Terakhir</dt>
                    <dd class="font-medium">{{ $user->last_login_at?->translatedFormat('d F Y H:i') ?? 'Belum pernah' }}</dd></div>
                <div><dt class="text-caption text-[var(--app-text-muted)]">Akun Dibuat</dt>
                    <dd class="font-medium">{{ $user->created_at->translatedFormat('d F Y') }}</dd></div>
                @if ($user->creator)
                    <div><dt class="text-caption text-[var(--app-text-muted)]">Dibuat Oleh</dt>
                        <dd class="font-medium">{{ $user->creator->name }}</dd></div>
                @endif
                <div><dt class="text-caption text-[var(--app-text-muted)]">Tautan Siswa</dt>
                    <dd class="font-medium">{{ $user->student?->nisn ?? '—' }}</dd></div>
            </dl>
        </x-card>

        {{-- ---------- Role + permissions ---------- --}}
        <x-card title="Role & Hak Akses" icon="shield-check"
                description="Izin yang dimiliki akun ini.">
            @can('role.assign')
                <form method="POST" action="{{ route('admin.users.role', $user) }}" class="flex flex-wrap items-end gap-2 mb-5">
                    @csrf
                    @method('PUT')
                    <div class="min-w-[200px]">
                        <label for="role-select" class="label">Ubah Role</label>
                        <select id="role-select" name="role" class="field" required>
                            @foreach ($assignableRoles as $role)
                                <option value="{{ $role }}" @selected($user->hasRole($role))>{{ ucfirst(str_replace('_', ' ', $role)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Simpan Role</button>
                </form>
            @endcan

            @php $granted = $user->getAllPermissions()->pluck('name')->sort()->values(); @endphp

            @if ($user->isSuperAdmin())
                <x-alert variant="info" title="Super Admin"
                         message="Memiliki seluruh izin secara otomatis, termasuk izin yang baru ditambahkan di kemudian hari." />
            @elseif ($granted->isEmpty())
                <x-alert variant="warning" title="Belum ada izin"
                         message="Akun ini belum diberi izin apa pun sehingga tidak dapat membuka halaman administrasi." />
            @else
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach ($granted as $permission)
                        <div class="flex items-center gap-2 text-small">
                            <x-icon name="check" class="w-3.5 h-3.5 text-[var(--app-success)] shrink-0" />
                            <span class="text-[var(--app-text)]">{{ $permission }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- ---------- Recent activity ---------- --}}
        <x-card title="Aktivitas Terakhir" icon="history" body-class="p-0 sm:p-0">
            <ul class="divide-y divide-[var(--app-border)]">
                @forelse ($activity as $log)
                    <li class="px-4 sm:px-5 py-3.5">
                        <p class="text-small font-medium text-[var(--app-text)]">{{ $log->description }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">
                            {{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->translatedFormat('d M Y H:i') }}
                        </p>
                    </li>
                @empty
                    <li class="px-5 py-8">
                        <x-empty-state icon="history" compact title="Belum ada aktivitas" />
                    </li>
                @endforelse
            </ul>
        </x-card>
    </div>

    {{-- ---------- Actions ---------- --}}
    <div class="space-y-4 sm:space-y-5">
        <x-card title="Tindakan" icon="settings">

            @can('user.update')
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-secondary w-full justify-center mb-2">
                    <x-icon name="user" class="w-4 h-4" />
                    Ubah Data
                </a>
            @endcan

            @can('user.disable')
                <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="mb-2"
                      x-data="{ off: {{ $user->isActive() ? 'true' : 'false' }}, reason: '' }">
                    @csrf
                    <template x-if="off">
                        <button type="submit" class="btn btn-danger w-full justify-center">
                            <x-icon name="x" class="w-4 h-4" />
                            Nonaktifkan Akun
                        </button>
                    </template>
                    <template x-if="!off">
                        <button type="submit" class="btn btn-success w-full justify-center">
                            <x-icon name="check" class="w-4 h-4" />
                            Aktifkan Kembali
                        </button>
                    </template>
                </form>
            @endcan

            {{-- Password reset is a separate form with its own confirmation --}}
            @can('user.reset_password')
                <details class="mt-2 group">
                    <summary class="btn btn-secondary w-full justify-center cursor-pointer list-none">
                        <x-icon name="lock" class="w-4 h-4" />
                        Atur Ulang Password
                    </summary>
                    <form method="POST" action="{{ route('admin.users.password', $user) }}" class="mt-3 space-y-2">
                        @csrf
                        @method('PUT')
                        <input class="field text-sm" type="password" name="password" required minlength="8"
                               placeholder="Password baru (min. 8)" autocomplete="new-password">
                        <input class="field text-sm" type="password" name="password_confirmation" required minlength="8"
                               placeholder="Ulangi password" autocomplete="new-password">
                        <p class="help-text">Pengguna akan memakai password ini untuk masuk berikutnya.</p>
                        <button type="submit" class="btn btn-primary btn-sm w-full justify-center">Simpan Password</button>
                    </form>
                </details>
            @endcan

            @can('user.delete')
                <div class="mt-4 pt-4 border-t border-[var(--app-border)]">
                    <button type="button" @click="$dispatch('confirm-open', 'del-user-{{ $user->id }}')"
                            class="btn btn-ghost w-full justify-center !text-[var(--app-danger)]">
                        <x-icon name="trash" class="w-4 h-4" />
                        Hapus Permanen
                    </button>
                    <form id="del-user-{{ $user->id }}" method="POST" action="{{ route('admin.users.destroy', $user) }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                </div>
            @endcan
        </x-card>

        <x-alert variant="info" title="Lebih aman menonaktifkan"
                 message="Menonaktifkan mempertahankan riwayat verifikasi dan audit. Hapus permanen hanya bila benar-benar diperlukan." />
    </div>
</div>

<x-confirm-dialog title="Hapus pengguna secara permanen?"
                 message="Akun {{ $user->name }} akan dihapus. Riwayat yang berkaitan dengannya tetap tersimpan, tetapi akun tidak dapat dipulihkan."
                 confirm-label="Ya, hapus permanen" />
@endsection
