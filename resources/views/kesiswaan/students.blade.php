@extends('components.app-shell')

@section('title', 'Data Siswa')
@section('page-title', 'Data Siswa')
@section('page-description', '{{ $students->total() }} siswa cocok dengan filter')

@section('page-actions')
    <a href="{{ route('laporan.index', request()->query()) }}" class="btn btn-secondary">
        <x-icon name="file-text" class="w-4 h-4" />
        Buat Laporan
    </a>
@endsection

@section('content')

{{-- ============ Filters ============ --}}
<form method="GET" class="surface p-4 mb-4">
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3">
        <div class="sm:col-span-2 xl:col-span-2">
            <label for="q" class="sr-only">Cari nama, NISN, atau NIK</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
                <input id="q" name="q" value="{{ request('q') }}" class="field pl-9"
                       placeholder="Cari nama, NISN, atau NIK">
            </div>
        </div>

        @php
            // value => label maps. Building these with ->map(fn ($l, $v) => [$v => $l])
            // produced ARRAY values, and echoing an array blows up with
            // "htmlspecialchars(): Argument #1 must be of type string, array given".
            $filters = [
                ['status', 'Semua status', \App\Models\Registration::STATUS_LABELS],
                ['class_id', 'Semua kelas', $options['classes']->all()],
                ['department_id', 'Semua jurusan', $options['departments']->all()],
                ['entry_year', 'Semua angkatan', $entryYears->flip()->all()],
            ];
        @endphp

        @foreach ($filters as [$name, $placeholder, $items])
            <div>
                <label for="{{ $name }}" class="sr-only">{{ $placeholder }}</label>
                <select id="{{ $name }}" name="{{ $name }}" class="field">
                    <option value="">{{ $placeholder }}</option>
                    @foreach ($items as $value => $label)
                        <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary flex-1 justify-center">
                <x-icon name="filter" class="w-4 h-4" />
                Terapkan
            </button>
            @if (request()->hasAny(['q', 'status', 'class_id', 'department_id', 'entry_year']))
                <a href="{{ route('kesiswaan.students') }}" class="btn btn-secondary shrink-0" aria-label="Reset filter">
                    <x-icon name="refresh-cw" class="w-4 h-4" />
                </a>
            @endif
        </div>
    </div>
</form>

{{-- ============ Desktop table ============ --}}
<div class="hidden lg:block surface-flush">
    <div class="overflow-x-auto scrollbar-thin">
        <table class="data-table">
            <thead>
                <tr>
                    <th>NISN</th>
                    <th>Nama</th>
                    <th>L/P</th>
                    <th>Angkatan</th>
                    <th>Kelas</th>
                    <th>Jurusan</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $s)
                    <tr>
                        <td class="tabular-nums text-[var(--app-text-muted)]">{{ $s->nisn }}</td>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="shrink-0 grid place-items-center w-8 h-8 rounded-full bg-brand-50 text-brand-700 text-[11px] font-bold">
                                    {{ strtoupper(mb_substr($s->full_name, 0, 2)) }}
                                </span>
                                <a href="{{ route('kesiswaan.students.show', $s) }}"
                                   class="font-medium text-[var(--app-text)] hover:underline truncate max-w-[200px] block">
                                    {{ $s->full_name }}
                                </a>
                            </div>
                        </td>
                        <td class="text-[var(--app-text-muted)]">{{ $s->genderLabel() }}</td>
                        <td class="text-[var(--app-text-muted)] tabular-nums">{{ $s->entry_year ?? '—' }}</td>
                        <td>{{ $s->schoolClass?->name ?? '—' }}</td>
                        <td class="text-[var(--app-text-muted)]">{{ $s->schoolClass?->department?->code ?? '—' }}</td>
                        <td><x-status-badge :status="$s->registration?->status ?? 'draft'" /></td>
                        <td class="text-right">
                            <a href="{{ route('kesiswaan.students.show', $s) }}" class="btn btn-sm btn-secondary">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-0">
                            <x-empty-state icon="users" class="py-12"
                                          title="Tidak ada siswa yang cocok"
                                          description="Coba ubah kata kunci atau atur ulang filter Anda.">
                                <x-slot:action>
                                    <a href="{{ route('kesiswaan.students') }}" class="btn btn-secondary btn-sm">Reset filter</a>
                                </x-slot:action>
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Tablet + mobile cards ============ --}}
<div class="lg:hidden space-y-3">
    @forelse ($students as $s)
        <article class="surface p-4">
            <div class="flex items-start gap-3">
                <span class="shrink-0 grid place-items-center w-11 h-11 rounded-full bg-brand-50 text-brand-700 text-small font-bold">
                    {{ strtoupper(mb_substr($s->full_name, 0, 2)) }}
                </span>
                <div class="min-w-0 flex-1">
                    <h3 class="text-body font-semibold text-[var(--app-text)] leading-snug">{{ $s->full_name }}</h3>
                    <p class="text-caption text-[var(--app-text-muted)] mt-0.5">NISN {{ $s->nisn }}</p>
                </div>
                <x-status-badge :status="$s->registration?->status ?? 'draft'" class="shrink-0" />
            </div>

            <dl class="mt-3.5 grid grid-cols-2 gap-y-2.5 text-small">
                <div>
                    <dt class="text-caption text-[var(--app-text-muted)]">Jenis Kelamin</dt>
                    <dd class="font-medium">{{ $s->genderLabel() }}</dd>
                </div>
                <div>
                    <dt class="text-caption text-[var(--app-text-muted)]">Angkatan</dt>
                    <dd class="font-medium tabular-nums">{{ $s->entry_year ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-caption text-[var(--app-text-muted)]">Kelas</dt>
                    <dd class="font-medium">{{ $s->schoolClass?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-caption text-[var(--app-text-muted)]">Jurusan</dt>
                    <dd class="font-medium">{{ $s->schoolClass?->department?->code ?? '—' }}</dd>
                </div>
            </dl>

            <a href="{{ route('kesiswaan.students.show', $s) }}" class="btn btn-secondary w-full justify-center mt-3.5">
                Lihat detail
                <x-icon name="chevron-right" class="w-4 h-4" />
            </a>
        </article>
    @empty
        <x-card>
            <x-empty-state icon="users" title="Tidak ada siswa yang cocok"
                          description="Coba ubah kata kunci atau atur ulang filter Anda." />
        </x-card>
    @endforelse
</div>

@if ($students->hasPages())
    <div class="mt-5">{{ $students->links() }}</div>
@endif
@endsection
