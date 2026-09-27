@extends('components.app-shell')

@section('title', 'Data Pengguna')
@section('heading', 'Data Pengguna')

@section('content')
<div class="card overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs text-slate-600 border-b">
            <tr><th class="px-4 py-3">Nama</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Ubah Role</th></tr>
        </thead>
        <tbody>
            @foreach ($users as $u)
                <tr class="border-b last:border-0">
                    <td class="px-4 py-3 font-medium">{{ $u->name }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $u->email }}</td>
                    <td class="px-4 py-3">{{ $u->roles->pluck('name')->implode(', ') ?: 'siswa' }}</td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex gap-2">
                            @csrf @method('PUT')
                            <select class="input !py-1 text-xs" name="role">
                                @foreach (['siswa', 'kesiswaan', 'admin'] as $role)
                                    <option value="{{ $role }}" @selected($u->hasRole($role))>{{ $role }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-secondary !py-1 !px-3 text-xs" type="submit">Simpan</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
