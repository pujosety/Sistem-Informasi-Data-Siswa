@extends('components.app-shell')

@section('title', 'Ringkasan Penempatan')
@section('page-title', 'Ringkasan Penempatan')
@section('page-description', $classroom->name.' \u00b7 '.$classroom->academicYear?->name)

@section('content')

<div class="mb-5 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
    <p class="text-sm text-slate-600">
        <span class="font-semibold text-slate-900">{{ count($ok) }}</span> siswa akan ditempatkan,
        <span class="font-semibold text-slate-900">{{ count($blocked) }}</span> dilewati.
    </p>

    @if ($remaining !== null && count($ok) > $remaining)
        <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Perhatian: kapasitas tersisa {{ $remaining }} kursi, sedangkan {{ count($ok) }} siswa dipilih.
            Penempatan tetap dilakukan, tetapi kelas akan melebihi kapasitas.
        </p>
    @endif
</div>

@if (count($ok))
    <section class="mb-6">
        <h2 class="mb-2 text-base font-semibold text-emerald-700">Akan ditempatkan ({{ count($ok) }})</h2>
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200 bg-white">
            @foreach ($ok as $student)
                <li class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                    <span class="font-medium text-slate-900">{{ $student->full_name }}</span>
                    <span class="font-mono text-xs text-slate-500">{{ $student->nisn }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if (count($blocked))
    <section class="mb-6">
        <h2 class="mb-2 text-base font-semibold text-amber-700">Dilewati ({{ count($blocked) }})</h2>
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl border border-amber-200 bg-amber-50/50">
            @foreach ($blocked as $b)
                <li class="px-4 py-2.5 text-sm">
                    <span class="font-medium text-slate-900">{{ $b['student']->full_name }}</span>
                    <span class="mt-0.5 block text-xs text-amber-800">{{ $b['reason'] }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif

<form method="POST" action="{{ route('academic.enrollments.store') }}"
      onsubmit="return confirm('Tempatkan {{ count($ok) }} siswa ke {{ $classroom->name }}?')">
    @csrf
    <input type="hidden" name="classroom_id" value="{{ $classroom->id }}">
    @foreach ($ok as $student)
        <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
    @endforeach

    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <a href="{{ route('academic.enrollments.create', ['tahun' => $classroom->academic_year_id, 'kelas' => $classroom->id]) }}"
           class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Kembali</a>
        <button @disabled(! count($ok))
                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">
            Konfirmasi Penempatan
        </button>
    </div>
</form>
@endsection
