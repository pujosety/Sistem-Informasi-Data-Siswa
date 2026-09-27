@extends('components.app-shell')

@section('title', 'Ubah '.$classroom->name)
@section('page-title', 'Ubah Kelas')
@section('page-description', $classroom->name.' \u00b7 '.$classroom->academicYear?->name)

@section('content')
<div class="mx-auto max-w-2xl">
    <form method="POST" action="{{ route('academic.classes.update', $classroom) }}"
          class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Nama Kelas</label>
                <input id="name" name="name" required value="{{ old('name', $classroom->name) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="code" class="mb-1 block text-sm font-medium text-slate-700">Kode</label>
                <input id="code" name="code" value="{{ old('code', $classroom->code) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="level" class="mb-1 block text-sm font-medium text-slate-700">Tingkat</label>
                <input id="level" name="level" required value="{{ old('level', $classroom->level) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="academic_year_id" class="mb-1 block text-sm font-medium text-slate-700">Tahun Ajaran</label>
                <select id="academic_year_id" name="academic_year_id" required
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($years as $y)
                        <option value="{{ $y->id }}" @selected($y->id === $classroom->academic_year_id)>{{ $y->name }}</option>
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
                        <option value="{{ $d->id }}" @selected($d->id === $classroom->department_id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="capacity" class="mb-1 block text-sm font-medium text-slate-700">Kapasitas</label>
                <input id="capacity" type="number" name="capacity" min="1" max="200" value="{{ old('capacity', $classroom->capacity) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('capacity') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="room" class="mb-1 block text-sm font-medium text-slate-700">Ruangan</label>
                <input id="room" name="room" value="{{ old('room', $classroom->room) }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $classroom->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('academic.classes.show', $classroom) }}"
               class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan Perubahan</button>
        </div>
    </form>

    @can('archive', $classroom)
        <form method="POST" action="{{ route('academic.classes.archive', $classroom) }}"
              onsubmit="return confirm('Arsipkan kelas ini? Riwayat siswa tetap tersedia.')"
              class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
            @csrf
            <p class="text-sm font-medium text-amber-900">Arsipkan kelas</p>
            <p class="mt-1 text-sm text-amber-800">
                Arsipkan tidak menghapus data. Siswa, absensi, dan enrollment tetap dapat dilihat untuk laporan historis.
            </p>
            <input type="text" name="reason" placeholder="Alasan (opsional)"
                   class="mt-3 w-full rounded-lg border-amber-300 text-sm">
            <button class="mt-3 rounded-lg bg-amber-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-amber-700">
                Arsipkan Kelas
            </button>
        </form>
    @endcan
</div>
@endsection
