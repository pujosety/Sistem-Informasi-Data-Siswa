@extends('components.app-shell')

@section('title', 'Tahun Ajaran')
@section('page-title', 'Tahun Ajaran')
@section('page-description', 'Kelola tahun ajaran aktif, mendatang, dan arsip')

@section('page-actions')
    <button type="button" onclick="document.getElementById('formTahun').showModal()"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
        <span aria-hidden="true">+</span> Tambah Tahun Ajaran
    </button>
@endsection

@section('content')

<div class="mb-5 flex flex-wrap items-center gap-2 text-sm">
    <span class="rounded-md bg-slate-100 px-2.5 py-1 font-medium text-slate-700">
        Aktif: {{ \App\Models\AcademicYear::current()?->name ?? 'Belum ada' }}
    </span>
    <span class="text-slate-500">Hanya satu tahun ajaran aktif pada satu waktu.</span>
</div>

{{-- Desktop table --}}
<div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:block">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3 font-semibold">Tahun Ajaran</th>
                <th class="px-4 py-3 font-semibold">Periode</th>
                <th class="px-4 py-3 font-semibold text-center">Kelas</th>
                <th class="px-4 py-3 font-semibold text-center">Siswa</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 text-right font-semibold">Aksi</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($years as $year)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 font-medium text-slate-900">
                        {{ $year->name }}
                        @if ($year->is_default)
                            <span class="ml-1.5 rounded bg-indigo-50 px-1.5 py-0.5 text-[11px] font-semibold text-indigo-700">UTAMA</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $year->start_date->format('d M Y') }} \u2013 {{ $year->end_date->format('d M Y') }}
                    </td>
                    <td class="px-4 py-3 text-center tabular-nums text-slate-700">{{ $year->classes_count }}</td>
                    <td class="px-4 py-3 text-center tabular-nums text-slate-700">{{ $year->enrollments_count }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$year->status" />
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="inline-flex items-center gap-1.5">
                            @if ($year->status !== \App\Models\AcademicYear::ACTIVE)
                                <form method="POST" action="{{ route('academic.years.activate', $year) }}">
                                    @csrf
                                    <button class="rounded-md bg-indigo-50 px-2.5 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100">
                                        Aktifkan
                                    </button>
                                </form>
                            @endif

                            @if ($year->status !== \App\Models\AcademicYear::ARCHIVED)
                                <form method="POST" action="{{ route('academic.years.archive', $year) }}"
                                      onsubmit="return confirm('Arsipkan {{ $year->name }}? Data historis tetap tersimpan.')">
                                    @csrf
                                    <button class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                                        Arsipkan
                                    </button>
                                </form>
                            @endif

                            <button type="button"
                                    class="edit-year rounded-md bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50"
                                    data-id="{{ $year->id }}"
                                    data-name="{{ $year->name }}"
                                    data-start="{{ $year->start_date->toDateString() }}"
                                    data-end="{{ $year->end_date->toDateString() }}"
                                    data-status="{{ $year->status }}"
                                    data-notes="{{ $year->notes }}">
                                Ubah
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                        Belum ada tahun ajaran. Tambahkan terlebih dahulu.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Mobile cards --}}
<div class="space-y-3 sm:hidden">
    @forelse ($years as $year)
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900">{{ $year->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $year->start_date->format('d M Y') }} \u2013 {{ $year->end_date->format('d M Y') }}
                    </p>
                </div>
                <x-status-badge :status="$year->status" />
            </div>

            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Kelas</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">{{ $year->classes_count }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 px-3 py-2">
                    <dt class="text-xs text-slate-500">Siswa</dt>
                    <dd class="font-semibold tabular-nums text-slate-800">{{ $year->enrollments_count }}</dd>
                </div>
            </dl>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($year->status !== \App\Models\AcademicYear::ACTIVE)
                    <form method="POST" action="{{ route('academic.years.activate', $year) }}">
                        @csrf
                        <button class="w-full rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white">Aktifkan</button>
                    </form>
                @endif
                @if ($year->status !== \App\Models\AcademicYear::ARCHIVED)
                    <form method="POST" action="{{ route('academic.years.archive', $year) }}"
                          onsubmit="return confirm('Arsipkan {{ $year->name }}?')">
                        @csrf
                        <button class="w-full rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700">Arsipkan</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
            Belum ada tahun ajaran.
        </p>
    @endforelse
</div>

{{-- Create / edit dialog --}}
<dialog id="formTahun" class="w-[min(32rem,92vw)] rounded-xl p-0 backdrop:bg-slate-900/40">
    {{--
    The action defaults to the CREATE route. It was absent, so submitting
    "Tambah Tahun Ajaran" POSTed to whatever URL the operator happened to be on
    — which is /akademik/tahun-ajaran (the index), where the only POST routes
    are activate and arsipkan. A 405 from a form that looks completely correct.

    The JS rewrites the action for the edit case; leaving a correct one as the
    default means the common path does not depend on that script having run.
--}}
<form id="tahunForm" method="POST" action="{{ route('academic.years.store') }}" class="rounded-xl bg-white">
        @csrf
        <input type="hidden" name="_method" id="tahunMethod" value="POST">
        <input type="hidden" name="id" id="tahunId">

        <div class="border-b border-slate-200 px-5 py-4">
            <h2 id="tahunTitle" class="text-base font-semibold text-slate-900">Tambah Tahun Ajaran</h2>
        </div>

        <div class="space-y-4 px-5 py-4">
            <div>
                <label for="tahunName" class="mb-1 block text-sm font-medium text-slate-700">Tahun Ajaran</label>
                <input id="tahunName" name="name" required placeholder="2026/2027"
                       pattern="@{{\d{4}/\d{4}}}" maxlength="20"
                       class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">Format: tahun/tahun, contoh 2026/2027</p>
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="tahunStart" class="mb-1 block text-sm font-medium text-slate-700">Tanggal Mulai</label>
                    <input id="tahunStart" type="date" name="start_date" required
                           class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('start_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="tahunEnd" class="mb-1 block text-sm font-medium text-slate-700">Tanggal Selesai</label>
                    <input id="tahunEnd" type="date" name="end_date" required
                           class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('end_date') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="tahunStatus" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="tahunStatus" name="status"
                        class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="tahunNotes" class="mb-1 block text-sm font-medium text-slate-700">Catatan</label>
                <textarea id="tahunNotes" name="notes" rows="2"
                          class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
            </div>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4">
            <button type="button" onclick="document.getElementById('formTahun').close()"
                    class="rounded-lg px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">Batal</button>
            <button type="submit" class="rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Simpan
            </button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
(function () {
    const dialog = document.getElementById('formTahun');
    const form = document.getElementById('tahunForm');
    const method = document.getElementById('tahunMethod');
    const id = document.getElementById('tahunId');
    const title = document.getElementById('tahunTitle');

    document.querySelectorAll('.edit-year').forEach(function (btn) {
        btn.addEventListener('click', function () {
            // The named route with a sentinel, substituted at click time.
            // The previous line built the URL by string-concatenating a literal
            // '/akademik/tahun-ajaran/', which is a 404 waiting for the day the
            // route moves, and nothing in a Blade file would show it.
            form.action = '{{ url('/akademik/tahun-ajaran') }}/' + btn.dataset.id;
            method.value = 'PUT';
            id.value = btn.dataset.id;
            title.textContent = 'Ubah Tahun Ajaran';
            form.name.value = btn.dataset.name;
            form.start_date.value = btn.dataset.start;
            form.end_date.value = btn.dataset.end;
            form.status.value = btn.dataset.status;
            form.notes.value = btn.dataset.notes || '';
            dialog.showModal();
        });
    });
})();
</script>
@endpush
@endsection
