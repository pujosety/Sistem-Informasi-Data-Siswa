@extends('components.app-shell')

@section('title', 'Pengumuman Baru')
@section('page-title', 'Buat Pengumuman')
@section('page-description', $classroom->name.' \u00b7 '.$classroom->academicYear?->name)

@section('content')
<div class="mx-auto max-w-2xl">
    <form method="POST" action="{{ route('academic.announcements.store', $classroom) }}"
          class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        @csrf

        <div>
            <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Judul</label>
            <input id="title" name="title" required maxlength="150" value="{{ old('title') }}"
                   placeholder="Pemberitahuan kegiatan kelas"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="body" class="mb-1 block text-sm font-medium text-slate-700">Isi Pengumuman</label>
            <textarea id="body" name="body" rows="6" required
                      placeholder="Tulis pengumuman untuk siswa dan/atau orang tua kelas ini."
                      class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            @error('body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="audience" class="mb-1 block text-sm font-medium text-slate-700">Audiens</label>
                <select id="audience" name="audience" required
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($audiences as $value => $label)
                        <option value="{{ $value }}" @selected(old('audience', 'both') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Hanya siswa dan wali kelas {{ $classroom->name }} yang akan diberi tahu.</p>
            </div>

            <div>
                <label for="expires_at" class="mb-1 block text-sm font-medium text-slate-700">Berakhir Pada</label>
                <input id="expires_at" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('expires_at') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-start gap-2.5 rounded-lg bg-slate-50 p-3">
            <input type="checkbox" name="publish" value="1" @checked(old('publish'))
                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            <span class="text-sm text-slate-700">
                <span class="font-medium">Langsungublikasikan</span>
                <span class="block text-xs text-slate-500">Jika tidak dicentang, pengumuman disimpan sebagai draf.</span>
            </span>
        </label>

        <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
            <a href="{{ route('academic.announcements.index', $classroom) }}"
               class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</a>
            <button class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Simpan Pengumuman</button>
        </div>
    </form>
</div>
@endsection
