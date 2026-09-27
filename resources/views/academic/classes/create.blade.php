@extends('components.app-shell')

@section('title', 'Buat Kelas')
@section('page-title', 'Buat Kelas')
@section('page-description', 'Kelas selalu berada dalam satu tahun ajaran')

@section('content')
<div class="mx-auto max-w-2xl">
    <form method="POST" action="{{ route('academic.classes.store') }}"
          class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nama Kelas</label>
                <input id="name" name="name" required value="{{ old('name') }}" placeholder="X RPL 1"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="code" class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input id="code" name="code" value="{{ old('code') }}" placeholder="RPL-10-1"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="level" class="mb-1 block text-sm font-medium text-slate-700">Tingkat</label>
                <input id="level" name="level" required value="{{ old('level') }}" placeholder="X"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('level') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="academic_year_id" class="mb-1 block text-sm font-medium text-slate-700">Tahun Ajaran</label>
                <select id="academic_year_id" name="academic_year_id" required
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($years as $y)
                        <option value="{{ $y->id }}" @selected((int) old('academic_year_id', $selectedYear) === $y->id)>
                            {{ $y->name }}@if ($y->status === 'active') (aktif) @endif
                        </option>
                    @endforeach
                </select>
                @error('academic_year_id') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="department_id" class="mb-1 block text-sm font-medium text-slate-700">Jurusan</label>
                <select id="department_id" name="department_id"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">\u2014 Tidak ditentukan</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) old('department_id') === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="capacity" class="mb-1 block text-sm font-medium text-slate-700">Kapasitas</label>
                <input id="capacity" type="number" name="capacity" min="1" max="200" value="{{ old('capacity', 36) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('capacity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="room" class="mb-1 block text-sm font-medium text-slate-700">Ruangan</label>
                <input id="room" name="room" value="{{ old('room') }}" placeholder="R-201"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('academic.classes.index') }}"
               class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan Kelas</button>
        </div>
    </form>
</div>
@endsection
