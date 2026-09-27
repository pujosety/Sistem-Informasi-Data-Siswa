{{-- Shared by the desktop table and the mobile cards so the two can never
     disagree about which roles are offered. --}}
@php $roleOptions = ['siswa', 'kesiswaan', 'admin']; @endphp

<form method="POST" action="{{ route('admin.users.role', $u) }}"
      class="flex items-center gap-2"
      onsubmit="return confirm('Ubah role {{ $u->name }}?')">
    @csrf
    @method('PUT')

    <label class="sr-only" for="role-{{ $u->id }}">Role untuk {{ $u->name }}</label>
    <select id="role-{{ $u->id }}" name="role"
            class="min-h-[40px] flex-1 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] px-2.5 text-small">
        @foreach ($roleOptions as $role)
            <option value="{{ $role }}" @selected($u->hasRole($role))>{{ $role }}</option>
        @endforeach
    </select>

    <button type="submit"
            class="min-h-[40px] shrink-0 rounded-[var(--radius-md)] bg-[var(--app-primary)] px-3 text-small font-semibold text-white">
        Simpan
    </button>
</form>
