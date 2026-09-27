@extends('components.app-shell')

@section('title', 'Absensi '.$classroom->name)
@section('page-title', 'Absensi')
@section('page-description', $classroom->name.' \u00b7 '.$date->translatedFormat('d F Y'))

@section('content')

<form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
    <div>
        <label for="date" class="mb-1 block text-xs font-medium text-slate-600">Tanggal</label>
        <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()"
               class="rounded-lg border-slate-300 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    </div>
    <a href="{{ route('academic.attendance.show', $classroom, today()->toDateString()) }}"
       class="rounded-lg bg-slate-100 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Hari Ini</a>
    <span class="ml-auto text-sm text-slate-500">
        {{ $enrollments->count() }} siswa
        @if ($locked) \u00b7 <span class="font-medium text-amber-700">Dikunci</span> @endif
    </span>
</form>

@if ($locked)
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        Sesi absensi ini sudah dikunci dan tidak dapat diubah.
    </div>
@endif

<form method="POST" action="{{ route('academic.attendance.store', $classroom) }}">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

    <div class="mb-3 flex flex-wrap gap-2">
        @foreach ($statuses as $value => $label)
            <button type="button" data-bulk="{{ $value }}"
                    @disabled($locked)
                    class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 disabled:opacity-40">
                Semua: {{ $label }}
            </button>
        @endforeach
        <button type="button" id="clearAll" @disabled($locked)
                class="rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50 disabled:opacity-40">
            Kosongkan
        </button>
    </div>

    <div class="space-y-2">
        @forelse ($enrollments as $enrollment)
            @php
                $student = $enrollment->student;
                $record = $records->get($enrollment->id);
                $current = $record?->status;
            @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm" data-row>
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium text-slate-900">{{ $student?->full_name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ $student?->nisn }}</p>
                    </div>
                    @if ($record?->wasCorrected())
                        <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700"
                              title="Dikoreksi dari {{ $record->previous_status }}">dikoreksi</span>
                    @endif
                </div>

                <div class="mt-2.5 grid grid-cols-5 gap-1.5" role="group" aria-label="Status kehadiran">
                    @foreach ($statuses as $value => $label)
                        <label class="cursor-pointer">
                            <input type="radio" class="peer sr-only"
                                   name="statuses[{{ $enrollment->id }}]" value="{{ $value }}"
                                   @checked($current === $value) @disabled($locked)>
                            <span class="flex items-center justify-center rounded-lg border border-slate-200 px-1 py-2 text-center text-xs font-medium text-slate-600
                                         peer-checked:border-indigo-600 peer-checked:bg-indigo-50 peer-checked:text-indigo-700">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach
                </div>

                @if (! $locked)
                    <input type="text" name="notes[{{ $enrollment->id }}]" value="{{ $record?->notes }}"
                           placeholder="Catatan (opsional)"
                           class="mt-2 w-full rounded-lg border-slate-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @elseif ($record?->notes)
                    <p class="mt-2 text-sm text-slate-500">{{ $record->notes }}</p>
                @endif
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Belum ada siswa aktif di kelas ini.
            </p>
        @endforelse
    </div>

    @can('manageAttendance', $classroom)
        <div class="sticky bottom-0 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
            <p class="text-sm text-slate-600">
                <span id="markedCount" class="font-semibold text-slate-900">0</span> siswa ditandai
            </p>
            <div class="flex gap-2">
                <a href="{{ route('academic.classes.show', $classroom) }}"
                   class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Kembali</a>
                <button @disabled($locked || $enrollments->isEmpty())
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-40">
                    Simpan Absensi
                </button>
            </div>
        </div>
    @endcan
@endsection
{{-- Locking is a separate form: nesting it inside the save form would submit
     the attendance payload with the lock request. --}}
@if (! $locked && $records->isNotEmpty())
    <form method="POST" action="{{ route('academic.attendance.lock', [$classroom, $session]) }}" class="mt-3 text-right"
          onsubmit="return confirm('Kunci absensi ini? Data tidak dapat diubah lagi.')">
        @csrf
        <button class="rounded-lg bg-white px-3.5 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50">
            Kunci Absensi
        </button>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    const rows = document.querySelectorAll('[data-row]');
    const counter = document.getElementById('markedCount');
    if (!rows.length) { return; }

    function sync() {
        let n = 0;
        rows.forEach(function (row) {
            if (row.querySelector('input[type=radio]:checked')) { n++; }
        });
        if (counter) { counter.textContent = n; }
    }

    rows.forEach(function (row) {
        row.querySelectorAll('input[type=radio]').forEach(function (r) {
            r.addEventListener('change', sync);
        });
    });

    document.querySelectorAll('[data-bulk]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const value = btn.dataset.bulk;
            rows.forEach(function (row) {
                const radio = row.querySelector('input[value="' + CSS.escape(value) + '"]');
                if (radio) { radio.checked = true; }
            });
            sync();
        });
    });

    const clear = document.getElementById('clearAll');
    if (clear) {
        clear.addEventListener('click', function () {
            rows.forEach(function (row) {
                row.querySelectorAll('input[type=radio]').forEach(function (r) { r.checked = false; });
            });
            sync();
        });
    }

    sync();
})();
</script>
@endpush
