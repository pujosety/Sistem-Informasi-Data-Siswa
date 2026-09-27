@extends('components.app-shell')

@section('title', 'Tempatkan Siswa')
@section('page-title', 'Tempatkan Siswa ke Kelas')
@section('page-description', 'Pilih kelas, lalu centang siswa yang akan ditempatkan')

@section('content')

<form method="GET" class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_1fr_auto] sm:items-end">
    <div>
        <label for="tahun" class="mb-1 block text-sm font-medium text-slate-700">Tahun Ajaran</label>
        <select id="tahun" name="tahun" onchange="this.form.submit()"
                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @foreach ($years as $y)
                <option value="{{ $y->id }}" @selected($y->id === $yearId)>{{ $y->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="kelas" class="mb-1 block text-sm font-medium text-slate-700">Kelas Tujuan</label>
        <select id="kelas" name="kelas" onchange="this.form.submit()"
                class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="">\u2014 Pilih kelas</option>
            @foreach ($classes as $c)
                <option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>

    <button class="rounded-lg bg-slate-100 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">Tampilkan</button>
</form>

@if (! $classroom)
    <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
        <p class="text-sm font-medium text-slate-700">Pilih kelas tujuan terlebih dahulu.</p>
        <p class="mt-1 text-sm text-slate-500">Siswa yang sudah punya enrollment aktif di tahun ajaran ini tidak akan ditampilkan.</p>
    </div>
@else
    @if ($students->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
            <p class="text-sm font-medium text-slate-700">Tidak ada siswa yang bisa ditempatkan.</p>
            <p class="mt-1 text-sm text-slate-500">
                Semua siswa sudah memiliki penempatan pada tahun ajaran {{ $classroom->academicYear?->name }}.
            </p>
        </div>
    @else
        <form method="POST" action="{{ route('academic.enrollments.preview') }}">
            @csrf
            <input type="hidden" name="classroom_id" value="{{ $classroom->id }}">

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ $classroom->name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $classroom->academicYear?->name }}
                        @if ($classroom->capacity)
                            \u00b7 {{ $classroom->studentCount() }} / {{ $classroom->capacity }} siswa
                        @endif
                    </p>
                </div>
                <div class="flex gap-2">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau NISN"
                           class="rounded-lg border-slate-300 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="hidden grid-cols-[auto_1fr_1fr] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500 sm:grid">
                    <span class="w-5"></span>
                    <span>Nama</span>
                    <span>NISN</span>
                </div>

                @foreach ($students as $student)
                    <label class="flex cursor-pointer items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0 hover:bg-slate-50">
                        <input type="checkbox" name="student_ids[]" value="{{ $student->id }}"
                               class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                               @checked(in_array($student->id, $selected))>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-slate-900">{{ $student->full_name }}</span>
                        </span>
                        <span class="font-mono text-xs text-slate-500">{{ $student->nisn }}</span>
                    </label>
                @endforeach
            </div>

            <div class="sticky bottom-0 mt-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
                <p class="text-sm text-slate-600">
                    <span id="selectedCount" class="font-semibold text-slate-900">0</span> siswa dipilih
                </p>
                <button id="submitBtn" disabled
                        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">
                    Lanjut ke Ringkasan
                </button>
            </div>
        </form>
    @endif
@endif

@push('scripts')
<script>
(function () {
    const boxes = document.querySelectorAll('input[name="student_ids[]"]');
    const count = document.getElementById('selectedCount');
    const button = document.getElementById('submitBtn');
    if (!boxes.length || !count || !button) { return; }

    function sync() {
        const n = Array.from(boxes).filter(function (b) { return b.checked; }).length;
        count.textContent = n;
        button.disabled = n === 0;
    }

    boxes.forEach(function (b) { b.addEventListener('change', sync); });
    sync();
})();
</script>
@endpush
@endsection
