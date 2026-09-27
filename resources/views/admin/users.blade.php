@extends('components.app-shell')

@section('title', 'Data Pengguna')
@section('page-title', 'Data Pengguna')
@section('page-description', $users->total().' akun internal')

@section('content')

{{-- Desktop keeps the table; a 4-column table at 360px is unreadable, so the
     phone gets cards. Horizontal scroll is reserved for genuinely tabular
     comparison data, not for a name list. --}}
<div class="hidden overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-sm sm:block">
    <table class="min-w-full divide-y divide-[var(--app-border)] text-small">
        <thead class="bg-[var(--app-surface-alt)] text-left text-caption uppercase tracking-wide text-[var(--app-text-muted)]">
            <tr>
                <th class="px-4 py-3 font-semibold">Nama</th>
                <th class="px-4 py-3 font-semibold">Email</th>
                <th class="px-4 py-3 font-semibold">Role</th>
                <th class="px-4 py-3 font-semibold">Ubah Role</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-[var(--app-border)]">
            @forelse ($users as $u)
                <tr class="hover:bg-[var(--app-surface-alt)]">
                    <td class="px-4 py-3 font-medium text-[var(--app-text)]">{{ $u->name }}</td>
                    <td class="px-4 py-3 text-[var(--app-text-secondary)]">{{ $u->email }}</td>
                    <td class="px-4 py-3">
                        @forelse ($u->roles as $role)
                            <span class="mr-1 inline-block rounded-full bg-[var(--app-surface-alt)] px-2 py-0.5 text-caption font-semibold">{{ $role->name }}</span>
                        @empty
                            <span class="text-caption text-[var(--app-text-muted)]">tanpa role</span>
                        @endforelse
                    </td>
                    <td class="px-4 py-3">
                        @include('admin.users._role-form', ['u' => $u])
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-small text-[var(--app-text-muted)]">Belum ada pengguna.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Mobile: cards --}}
<div class="space-y-3 sm:hidden">
    @forelse ($users as $u)
        <article class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-4 shadow-sm">
            <p class="font-semibold text-[var(--app-text)]">{{ $u->name }}</p>
            <p class="mt-0.5 text-caption text-[var(--app-text-muted)]">{{ $u->email }}</p>

            <div class="mt-2.5 flex flex-wrap gap-1">
                @forelse ($u->roles as $role)
                    <span class="rounded-full bg-[var(--app-surface-alt)] px-2 py-0.5 text-caption font-semibold">{{ $role->name }}</span>
                @empty
                    <span class="text-caption text-[var(--app-text-muted)]">tanpa role</span>
                @endforelse
            </div>

            <div class="mt-3 border-t border-[var(--app-border)] pt-3">
                @include('admin.users._role-form', ['u' => $u])
            </div>
        </article>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-small text-slate-500">Belum ada pengguna.</p>
    @endforelse
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
