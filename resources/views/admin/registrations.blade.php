@extends('components.app-shell')

@section('title', 'Pendaftaran')
@section('page-title', 'Manajemen Pendaftaran')
@section('page-description', '{{ $students->total() }} pendaftar cocok dengan filter')

@section('page-actions')
    <a href="{{ route('admin.registrations', array_merge(request()->query(), ['status' => 'pending'])) }}"
       class="btn btn-primary">
        <x-icon name="clipboard-check" class="w-4 h-4" />
        Antrean Verifikasi
    </a>
@endsection

@section('content')

{{-- ============ Filters ============ --}}
<form method="GET" class="surface p-4 mb-4" data-filter-panel>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div class="sm:col-span-2">
            <label for="q" class="sr-only">Cari nama, NISN, atau NIK</label>
            <div class="relative">
                <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
                <input id="q" name="q" value="{{ request('q') }}" class="field pl-9"
                       placeholder="Cari nama, NISN, atau NIK">
            </div>
        </div>

        <div>
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="field">
                <option value="">Semua status</option>
                @foreach (\App\Models\Registration::STATUS_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="class_id" class="sr-only">Kelas</label>
            <select id="class_id" name="class_id" class="field">
                <option value="">Semua kelas</option>
                @foreach ($options['classes'] as $id => $name)
                    <option value="{{ $id }}" @selected((string) request('class_id') === (string) $id)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="gender" class="sr-only">Jenis kelamin</label>
            <select id="gender" name="gender" class="field">
                <option value="">Semua jenis kelamin</option>
                <option value="L" @selected(request('gender') === 'L')>Laki-laki</option>
                <option value="P" @selected(request('gender') === 'P')>Perempuan</option>
            </select>
        </div>

        <div>
            <label for="from" class="sr-only">Dari tanggal</label>
            <input id="from" type="date" name="from" value="{{ request('from') }}" class="field" title="Dari tanggal">
        </div>

        <div>
            <label for="to" class="sr-only">Sampai tanggal</label>
            <input id="to" type="date" name="to" value="{{ request('to') }}" class="field" title="Sampai tanggal">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn btn-primary flex-1 justify-center">
                <x-icon name="filter" class="w-4 h-4" />
                Terapkan
            </button>
            @if (request()->hasAny(['q', 'status', 'class_id', 'gender', 'from', 'to']))
                <a href="{{ route('admin.registrations') }}" class="btn btn-secondary shrink-0" aria-label="Reset filter">
                    <x-icon name="refresh-cw" class="w-4 h-4" />
                </a>
            @endif
        </div>
    </div>
</form>

{{-- ============ Desktop table ============ --}}
<div class="hidden md:block surface-flush">
    <div class="overflow-x-auto scrollbar-thin">
        <table class="data-table">
            <thead>
                <tr>
                    @php
                        $sort = request('sort', 'created_at');
                        $dir = request('direction', 'desc');
                        $th = function (string $key, string $label) use ($sort, $dir) {
                            $active = $sort === $key;
                            $next = $active && $dir === 'desc' ? 'asc' : 'desc';
                            return '<th><a href="'.route('admin.registrations', array_merge(request()->query(), ['sort' => $key, 'direction' => $next])).'" class="inline-flex items-center gap-1 hover:text-[var(--app-text)]">'.$label.'</a></th>';
                        };
                    @endphp
                    {!! $th('full_name', 'Nama') !!}
                    <th>NISN</th>
                    <th>Tanggal Daftar</th>
                    {!! $th('completeness', 'Kelengkapan') !!}
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $s)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <span class="shrink-0 grid place-items-center w-8 h-8 rounded-full bg-brand-50 text-brand-700 text-[11px] font-bold">
                                    {{ strtoupper(mb_substr($s->full_name, 0, 2)) }}
                                </span>
                                <div class="min-w-0">
                                    <a href="{{ route('admin.registrations.show', $s) }}"
                                       class="font-medium text-[var(--app-text)] hover:underline block truncate max-w-[220px]">
                                        {{ $s->full_name }}
                                    </a>
                                    <span class="text-caption text-[var(--app-text-muted)]">{{ $s->schoolClass?->name ?? 'Belum ditempatkan' }}</span>
                                </div>
                            </div>
                        </td>
                        <td class="text-[var(--app-text-muted)] tabular-nums">{{ $s->nisn }}</td>
                        <td class="text-[var(--app-text-muted)] whitespace-nowrap">
                            {{ $s->registration?->created_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <div class="flex items-center gap-2">
                                <div class="w-14 h-1.5 rounded-full bg-[var(--app-surface-muted)] overflow-hidden">
                                    <div class="h-full rounded-full"
                                         style="width: {{ $s->registration?->completeness ?? 0 }}%;
                                                background: {{ ($s->registration?->completeness ?? 0) >= 100 ? 'var(--app-success)' : 'var(--app-primary)' }}"></div>
                                </div>
                                <span class="text-caption tabular-nums text-[var(--app-text-muted)]">{{ $s->registration?->completeness ?? 0 }}%</span>
                            </div>
                        </td>
                        <td><x-status-badge :status="$s->registration?->status ?? 'draft'" /></td>
                        <td class="text-right">
                            <a href="{{ route('admin.registrations.show', $s) }}" class="btn btn-sm btn-secondary">
                                Periksa
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-0">
                            <x-empty-state icon="inbox" class="py-12"
                                          title="Tidak ada pendaftaran yang cocok"
                                          description="Coba ubah kata kunci atau atur ulang filter Anda.">
                                <x-slot:action>
                                    <a href="{{ route('admin.registrations') }}" class="btn btn-secondary btn-sm">Reset filter</a>
                                </x-slot:action>
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ============ Mobile cards (NOT a squashed table) ============ --}}
<div class="md:hidden space-y-3">
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
                    <dt class="text-caption text-[var(--app-text-muted)]">Kelengkapan</dt>
                    <dd class="font-semibold tabular-nums">{{ $s->registration?->completeness ?? 0 }}%</dd>
                </div>
                <div>
                    <dt class="text-caption text-[var(--app-text-muted)]">Terdaftar</dt>
                    <dd class="font-medium">{{ $s->registration?->created_at->format('d/m/Y') }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-caption text-[var(--app-text-muted)]">Kelas</dt>
                    <dd class="font-medium">{{ $s->schoolClass?->name ?? 'Belum ditempatkan' }}</dd>
                </div>
            </dl>

            <a href="{{ route('admin.registrations.show', $s) }}"
               class="btn btn-primary w-full justify-center mt-3.5">
                Periksa pendaftaran
                <x-icon name="chevron-right" class="w-4 h-4" />
            </a>
        </article>
    @empty
        <x-card>
            <x-empty-state icon="inbox" title="Tidak ada pendaftaran yang cocok"
                          description="Coba ubah kata kunci atau atur ulang filter Anda." />
        </x-card>
    @endforelse
</div>

@if ($students->hasPages())
    <div class="mt-5">{{ $students->links() }}</div>
@endif
@endsection
