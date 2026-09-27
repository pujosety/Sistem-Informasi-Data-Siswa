@extends('components.app-shell')

@section('title', 'Pindahkan '.$student->full_name)
@section('page-title', 'Pindahkan Siswa')
@section('page-description', $student->full_name.' \u00b7 saat ini di '.$current->classroom?->name)

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-600">
            Kelas saat ini: <span class="font-semibold text-slate-900">{{ $current->classroom?->name ?? '—' }}</span>
            ({{ $current->academicYear?->name }})
        </p>
    </div>

    <form method="POST" action="{{ route('academic.enrollments.move.store', $student) }}"
          class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf

        @if ($targets->isEmpty())
            <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Tidak ada kelas lain yang tersedia pada tahun ajaran ini.
            </p>
        @else
            <div>
                <label for="classroom_id" class="mb-1 block text-sm font-medium text-slate-700">Kelas Tujuan</label>
                <select id="classroom_id" name="classroom_id" required
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">\u2014 Pilih kelas</option>
                    @foreach ($targets as $t)
                        <option value="{{ $t->id }}">
                            {{ $t->name }}@if ($t->capacity) (sisa {{ $t->remainingCapacity() }} kursi) @endif
                        </option>
                    @endforeach
                </select>
                @error('classroom_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        @endif

        <div>
            <label for="effective_date" class="mb-1 block text-sm font-medium text-slate-700">Tanggal Berlaku</label>
            <input id="effective_date" type="date" name="effective_date" value="{{ old('effective_date', now()->toDateString()) }}"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div>
            <label for="reason" class="mb-1 block text-sm font-medium text-slate-700">Alasan</label>
            <textarea id="reason" name="reason" rows="2" required
                      placeholder="Contoh: Keterbatasan kapasitas kelas asal"
                      class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            <p class="mt-1 text-xs text-slate-500">Alasan tersimpan pada riwayat dan timeline siswa.</p>
            @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('academic.classes.show', $current->classroom) }}"
               class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</a>
            <button @disabled($targets->isEmpty()) class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Pindahkan Siswa
            </button>
        </div>
    </form>
</div>
@endsection
