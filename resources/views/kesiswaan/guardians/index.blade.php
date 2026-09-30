@extends('components.app-shell')

@section('title', 'Wali Murid — '.$student->full_name)
@section('page-title', 'Wali Murid')
@section('page-description', $student->full_name.' · NISN '.$student->nisn)

@section('page-actions')
    <a href="{{ route('kesiswaan.students.show', $student) }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali ke data siswa
    </a>
@endsection

@section('content')

<div class="grid xl:grid-cols-3 gap-4 sm:gap-5">

    {{-- ============ Current links ============ --}}
    <div class="xl:col-span-2 space-y-4 sm:space-y-5">
        <x-card title="Tautan Akun" icon="users"
                description="Akun yang dapat membuka portal orang tua untuk siswa ini.">
            @if ($links->isEmpty())
                <x-empty-state icon="user-x" class="py-10"
                              title="Belum ada wali murid tertaut"
                              description="Tanpa tautan, akun orang tua tidak dapat membuka portal sama sekali." />
            @else
                <div class="overflow-x-auto scrollbar-thin">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Akun</th>
                                <th>Hubungan</th>
                                <th>Status</th>
                                <th>Ditautkan</th>
                                <th class="text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($links as $link)
                                <tr>
                                    <td>
                                        <div class="font-medium text-[var(--app-text)]">{{ $link->guardianUser?->name ?? '—' }}</div>
                                        <div class="text-caption text-[var(--app-text-muted)]">{{ $link->guardianUser?->email }}</div>
                                        @if ($link->is_primary)
                                            <span class="mt-1 inline-block text-[10px] font-semibold uppercase tracking-wide text-brand-700 bg-brand-50 rounded px-1.5 py-0.5">
                                                Kontak utama
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $link->relationshipLabel() }}</td>
                                    <td>
                                        @if ($link->status === \App\Models\GuardianRelationship::ACTIVE)
                                            <span class="text-caption text-[var(--app-success)]">Aktif</span>
                                        @else
                                            <span class="text-caption text-[var(--app-text-muted)]">{{ ucfirst($link->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-[var(--app-text-muted)] tabular-nums">
                                        {{ $link->created_at?->format('d M Y') }}
                                    </td>
                                    <td class="text-right">
                                        @can('unlink', [\App\Policies\GuardianPolicy::class, $student])
                                            <form method="POST"
                                                  action="{{ route('kesiswaan.guardians.destroy', [$student, $link]) }}"
                                                  onsubmit="return confirm('Lepas tautan akun ini dari {{ $student->full_name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">Lepas</button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($links->where('status', \App\Models\GuardianRelationship::ACTIVE)->count() <= 1)
                    <div class="mt-4">
                        <x-alert variant="warning" title="Wali murid terakhir"
                                 message="Siswa ini hanya memiliki satu wali murid aktif. Tautan terakhir tidak dapat dilepas — tautkan wali murid lain terlebih dahulu." />
                    </div>
                @endif
            @endif
        </x-card>
    </div>

    {{-- ============ Link form ============ --}}
    <div class="space-y-4 sm:space-y-5">
        @can('link', [\App\Policies\GuardianPolicy::class, $student])
            <x-card title="Tautkan Akun" icon="user-plus"
                    description="Pilih akun orang tua/wali yang sudah terdaftar.">
                <form method="POST" action="{{ route('kesiswaan.guardians.store', $student) }}" class="space-y-3.5">
                    @csrf

                    <x-form-field name="guardian_user_id" label="Akun orang tua/wali" type="select" required
                                  placeholder="— Pilih akun —">
                        @foreach ($candidates as $account)
                            <option value="{{ $account->id }}" @selected(old('guardian_user_id') == $account->id)>
                                {{ $account->name }} — {{ $account->email }}
                            </option>
                        @endforeach
                    </x-form-field>

                    @error('guardian_user_id')
                        <p class="text-caption text-[var(--app-danger)]">{{ $message }}</p>
                    @enderror

                    <x-form-field name="relationship" label="Hubungan" type="select" required>
                        @foreach ($relations as $value => $label)
                            <option value="{{ $value }}" @selected(old('relationship', 'wali') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-form-field>

                    <label class="flex items-center gap-2 text-body">
                        <input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))
                               class="rounded border-[var(--app-border)]">
                        Jadikan kontak utama
                    </label>

                    <button type="submit" class="btn btn-primary w-full justify-center" @disabled($candidates->isEmpty())>
                        <x-icon name="link" class="w-4 h-4" />
                        Tautkan
                    </button>
                </form>
            </x-card>
        @endcan
    </div>

</div>

@endsection
