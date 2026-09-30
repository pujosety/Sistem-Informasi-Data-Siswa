{{--
    Shared announcement form.

    Both create and edit render this, so the field set cannot drift between
    them. $submitAction, $submitLabel and $announcement are supplied by the
    controller; $announcement is null when creating.

    Value resolution is old() first, then the stored record. After a failed
    validation the operator must see EXACTLY what they typed, not what the
    database still holds.
--}}
<form method="POST" action="{{ $submitAction }}"
      class="space-y-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    @csrf
    @isset($method)
        @method($method)
    @endisset

    <div>
        <label for="title" class="mb-1 block text-sm font-medium text-slate-700">Judul</label>
        <input id="title" name="title" required maxlength="150"
               value="{{ old('title', $announcement->title ?? '') }}"
               placeholder="Pemberitahuan kegiatan kelas"
               class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('title') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="body" class="mb-1 block text-sm font-medium text-slate-700">Isi Pengumuman</label>
        <textarea id="body" name="body" rows="6" required
                  placeholder="Tulis pengumuman untuk siswa dan/atau orang tua kelas ini."
                  class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('body', $announcement->body ?? '') }}</textarea>
        @error('body') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
    </div>

    <div class="grid gap-5 sm:grid-cols-2">
        <div>
            <label for="audience" class="mb-1 block text-sm font-medium text-slate-700">Audiens</label>
            <select id="audience" name="audience" required
                    class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @foreach ($audiences as $value => $label)
                    <option value="{{ $value }}" @selected(old('audience', $announcement->audience ?? 'both') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('audience')
                {{-- The one field that had no error display. Its custom message
                     ("Audiens tidak valid.") was raised by the controller and
                     flashed to the session, and then never rendered anywhere,
                     so a teacher who sent a bad value was shown the form again
                     with no explanation of what was wrong. --}}
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-xs text-slate-500">Hanya siswa dan wali kelas {{ $classroom->name }} yang akan diberi tahu.</p>
        </div>

        <div>
            <label for="expires_at" class="mb-1 block text-sm font-medium text-slate-700">Berakhir Pada</label>
            {{-- datetime-local needs "Y-m-d\TH:i", not a database cast. --}}
            <input id="expires_at" type="datetime-local" name="expires_at"
                   value="{{ old('expires_at', $announcement?->expires_at?->format('Y-m-d\TH:i')) }}"
                   class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            @error('expires_at') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="flex items-start gap-2.5 rounded-lg bg-slate-50 p-3">
        <input type="checkbox" name="publish" value="1"
               @checked(old('publish', $announcement?->published_at !== null))
               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
        <span class="text-sm text-slate-700">
            <span class="font-medium">Langsungublikasikan</span>
            <span class="block text-xs text-slate-500">
                @if ($announcement?->published_at)
                    Beri tahu kembali hanya bila isi atau audiens benar-benar berubah.
                @else
                    Jika tidak dicentang, pengumuman disimpan sebagai draf.
                @endif
            </span>
        </span>
    </label>

    <div class="flex flex-col-reverse gap-2 border-t border-slate-100 pt-4 sm:flex-row sm:justify-end">
        <a href="{{ route('academic.announcements.index', $classroom) }}"
           class="rounded-lg px-3.5 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</a>
        <button class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">{{ $submitLabel }}</button>
    </div>
</form>
