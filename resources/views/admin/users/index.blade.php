@extends('components.app-shell')

@section('title', 'Pengguna')
@section('page-title', 'Pengguna')
@section('page-description', $users->total().' akun internal')

@section('page-actions')
    @can('user.create')
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
            <x-icon name="user-plus" class="w-4 h-4" />
            Tambah Pengguna
        </a>
    @endcan
@endsection

@section('content')

<form method="GET" class="surface p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
    <div class="lg:col-span-2">
        <label for="q" class="sr-only">Cari pengguna</label>
        <div class="relative">
            <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
            <input id="q" name="q" value="{{ $q }}" class="field pl-9" placeholder="Cari nama atau email">
        </div>
    </div>
    <div>
        <label for="role" class="sr-only">Role</label>
        <select id="role" name="role" class="field">
            <option value="">Semua role</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected(($filters['role'] ?? '') === $role)>{{ ucfirst($role) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="status" class="sr-only">Status</label>
        <select id="status" name="status" class="field">
            <option value="">Semua status</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Aktif</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Nonaktif</option>
        </select>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary flex-1 justify-center" type="submit">Filter</button>
        <a href="{{ route('admin.users.index') }}" class="btn btn-secondary shrink-0" aria-label="Reset">
            <x-icon name="refresh-cw" class="w-4 h-4" />
        </a>
    </div>
</form>

{{-- ---------- Desktop table ---------- --}}
<div class="hidden lg:block surface-flush">
    <div class="overflow-x-auto scrollbar-thin">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nama</th><th>Email</th><th>Role</th><th>Status</th>
                    <th>Login Terakhir</th><th>Dibuat</th><th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $u)
                    <tr class="{{ $u->is_active() ? '' : 'opacity-60' }}">
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span @class([
                                    'shrink-0 grid place-items-center w-8 h-8 rounded-full text-[11px] font-bold',
                                    'bg-brand-50 text-brand-700' => $u->isActive(),
                                    'bg-ink-100 text-ink-500' => ! $u->isActive(),
                                ])>{{ strtoupper(mb_substr($u->name, 0, 2)) }}</span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.users.show', $u) }}"
                                       class="font-medium text-[var(--app-text)] hover:underline block truncate max-w-[200px]">{{ $u->name }}</a>
                                    @if ($u->isLastSuperAdmin())
                                        <span class="text-caption text-[var(--app-warning)]">Super Admin terakhir</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="text-[var(--app-text-muted)]">{{ $u->email }}</td>
                        <td>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($u->roles as $role)
                                    <span @class([
                                        'badge',
                                        'badge-brand' => $role->name === 'super_admin',
                                        'badge-info'  => in_array($role->name, ['admin', 'kesiswaan'], true),
                                        'badge-neutral' => in_array($role->name, ['operator', 'verifikator'], true),
                                        'badge-success' => $role->name === 'siswa',
                                    ])>{{ ucfirst($role->name) }}</span>
                                @endforeach
                            </div>
                        </td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-success' => $u->isActive(),
                                'badge-danger'  => ! $u->isActive(),
                            ])>
                                <x-icon :name="$u->isActive() ? 'check-circle' : 'x'" class="w-3 h-3" />
                                {{ $u->isActive() ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-caption text-[var(--app-text-muted)]">
                            {{ $u->last_login_at?->diffForHumans() ?? 'Belum pernah' }}
                        </td>
                        <td class="text-caption text-[var(--app-text-muted)]">{{ $u->created_at->format('d/m/Y') }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.users.show', $u) }}" class="btn btn-sm btn-secondary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-0">
                        <x-empty-state icon="users" class="py-12" title="Tidak ada pengguna"
                                      description="Coba ubah kata kunci atau filter Anda." />
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ---------- Mobile cards ---------- --}}
<div class="lg:hidden space-y-3">
    @forelse ($users as $u)
        <article @class(['surface p-4', 'opacity-60' => ! $u->isActive()])>
            <div class="flex items-start gap-3">
                <span @class([
                    'shrink-0 grid place-items-center w-11 h-11 rounded-full text-small font-bold',
                    'bg-brand-50 text-brand-700' => $u->isActive(),
                    'bg-ink-100 text-ink-500' => ! $u->isActive(),
                ])>{{ strtoupper(mb_substr($u->name, 0, 2)) }}</span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-body font-semibold text-[var(--app-text)] leading-snug">{{ $u->name }}</h3>
                    <p class="text-caption text-[var(--app-text-muted)] mt-0.5 break-all">{{ $u->email }}</p>
                </div>
                <span @class([
                    'badge shrink-0',
                    'badge-success' => $u->isActive(),
                    'badge-danger'  => ! $u->isActive(),
                ])>{{ $u->isActive() ? 'Aktif' : 'Nonaktif' }}</span>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($u->roles as $role)
                    <span @class([
                        'badge',
                        'badge-brand' => $role->name === 'super_admin',
                        'badge-info'  => in_array($role->name, ['admin', 'kesiswaan'], true),
                        'badge-neutral' => in_array($role->name, ['operator', 'verifikator'], true),
                        'badge-success' => $role->name === 'siswa',
                    ])>{{ ucfirst($role->name) }}</span>
                @endforeach
            </div>

            <p class="mt-3 text-caption text-[var(--app-text-muted)]">
                Login terakhir: {{ $u->last_login_at?->diffForHumans() ?? 'belum pernah' }}
            </p>

            <a href="{{ route('admin.users.show', $u) }}" class="btn btn-secondary w-full justify-center mt-3">
                Kelola pengguna
                <x-icon name="chevron-right" class="w-4 h-4" />
            </a>
        </article>
    @empty
        <x-card>
            <x-empty-state icon="users" title="Tidak ada pengguna"
                          description="Coba ubah kata kunci atau filter Anda." />
        </x-card>
    @endforelse
</div>

@if ($users->hasPages())
    <div class="mt-5">{{ $users->links() }}</div>
@endif
@endsection
