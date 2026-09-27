@extends('components.app-shell')

@section('title', 'Kelas')
@section('page-title', 'Kelas')
@section('page-description', $classes->count().' kelas pada tahun ajaran yang dipilih')

@section('page-actions')
    @can('classroom.create')
        <a href="{{ route('academic.classes.create', ['tahun' => $yearId]) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            <span aria-hidden="true">+</span> Buat Kelas
        </a>
    @endcan
    @can('enrollment.assign')
        <a href="{{ route('academic.enrollments.create', ['tahun' => $yearId]) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Tempatkan Siswa
        </a>
    @endcan
@endsection

@section('content')

<form method="GET" class="mb-4 flex flex-wrap items-end gap-2.5">
    <div>
        <label for="tahun" class="mb-1 block text-xs font-medium text-slate-600">Tahun Ajaran</label>
        <select id="tahun" name="tahun" onchange="this.form.submit()"
                class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($years as $y)
                <option value="{{ $y->id }}" @selected($y->id === $yearId)>{{ $y->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="min-w-48 flex-1">
        <label for="q" class="mb-1 block text-xs font-medium text-slate-600">Cari</label>
        <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Nama kelas, kode, atau ruang"
               class="w-full rounded-lg border-slate-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>

    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-slate-600">Status</label>
        <select id="status" name="status" onchange="this.form.submit()"
                class="rounded-lg border-slate-300 py-2 pl-3 pr-8 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">Semua</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <button class="rounded-lg bg-slate-100 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Filter</button>
</form>

{{-- Desktop grid --}}
<div class="hidden gap-4 sm:grid sm:grid-cols-2 xl:grid-cols-3">
    @forelse ($classes as $class)
        @php
            $homeroom = $class->homeroomAssignments->first()?->user;
            $count = $class->students_count;
            $full = $class->capacity && $count >= $class->capacity;
        @endphp
        <article class="flex flex-col rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="text-base font-semibold text-slate-900">{{ $class->name }}</h3>
                    <p class="mt-0.5 text-sm text-slate-500">
                        {{ $class->academicYear?->name }}
                        @if ($class->department) \u00b7 {{ $class->department->code }} @endif
                    </p>
                </div>
                <x-status-badge :status="$class->status" />
            </div>

            <dl class="mt-3.5 space-y-1.5 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Siswa</dt>
                    <dd class="font-medium tabular-nums {{ $full ? 'text-amber-700' : 'text-slate-800' }}">
                        {{ $count }}{{ $class->capacity ? ' / '.$class->capacity : '' }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Wali Kelas</dt>
                    <dd class="font-medium text-slate-800">{{ $homeroom?->name ?? 'Belum ditentukan' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Ruang</dt>
                    <dd class="font-medium text-slate-800">{{ $class->room ?: '\u2014' }}</dd>
                </div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                <a href="{{ route('academic.classes.show', $class) }}"
                   class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-medium text-white hover:bg-slate-800">Buka</a>
                @can('update', $class)
                    <a href="{{ route('academic.classes.edit', $class) }}"
                       class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Ubah</a>
                @endcan
                @can('assignHomeroom', $class)
                    <a href="{{ route('academic.classes.show', $class) }}#wali-kelas"
                       class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Wali Kelas</a>
                @endcan
                @can('viewReports', $class)
                    <a href="{{ route('academic.classes.show', $class) }}#laporan"
                       class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Laporan</a>
                @endcan
            </div>
        </article>
    @empty
        <p class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
            Belum ada kelas pada tahun ajaran ini.
        </p>
    @endforelse
</div>

{{-- Mobile list --}}
<div class="space-y-3 sm:hidden">
    @forelse ($classes as $class)
        @php $homeroom = $class->homeroomAssignments->first()?->user; @endphp
        <a href="{{ route('academic.classes.show', $class) }}"
           class="block rounded-xl border border-slate-200 bg-white p-4 shadow-sm active:bg-slate-50">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <p class="font-semibold text-slate-900">{{ $class->name }}</p>
                    <p class="text-xs text-slate-500">{{ $class->academicYear?->name }}</p>
                </div>
                <x-status-badge :status="$class->status" />
            </div>
            <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-600">
                <span><span class="font-medium tabular-nums text-slate-800">{{ $class->students_count }}</span> siswa</span>
                <span>Wali: <span class="font-medium text-slate-800">{{ $homeroom?->name ?? '\u2014' }}</span></span>
            </div>
        </a>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            Belum ada kelas.
        </p>
    @endforelse
</div>
@endsection
