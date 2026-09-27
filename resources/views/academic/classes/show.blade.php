@extends('components.app-shell')

@section('title', $classroom->name)
@section('page-title', $classroom->name)
@section('page-description', trim($classroom->academicYear?->name . ' · ' . ($classroom->department?->name ?? 'Tanpa jurusan') . ' · Tingkat ' . $classroom->level, ' · '))

@section('page-actions')
    @can('viewAttendance', $classroom)
        <a href="{{ route('academic.attendance.show', $classroom) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-800">
            Isi Absensi
        </a>
    @endcan
    @can('manageStudents', $classroom)
        <a href="{{ route('academic.enrollments.create', ['tahun' => $classroom->academic_year_id, 'kelas' => $classroom->id]) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            + Tempatkan Siswa
        </a>
    @endcan
    @can('update', $classroom)
        <a href="{{ route('academic.classes.edit', $classroom) }}"
           class="inline-flex items-center gap-2 rounded-lg bg-slate-100 px-3.5 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
            Ubah
        </a>
    @endcan
@endsection

@section('content')

{{-- ---------------------------------------------------------------- --}}
{{-- Overview                                                          --}}
{{-- ---------------------------------------------------------------- --}}
<div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <x-stat-card label="Siswa" :value="$stats['students']" :hint="$stats['capacity'] ? 'dari '.$stats['capacity'].' kursi' : null" />
    <x-stat-card label="Kehadiran Hari Ini"
                 :value="$presentToday === null ? '\\u2014' : $presentToday.' / '.$stats['students']"
                 :hint="$presentToday === null ? 'Belum diisi' : null" />
    <x-stat-card label="Data Belum Lengkap" :value="$stats['incomplete']" tone="warning" />
    <x-stat-card label="Wali Kelas" :value="$homeroom?->name ?? 'Belum ada'" />
</div>

<div class="mb-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-1.5 px-4 py-3 text-sm sm:px-5">
        <span class="text-slate-500">Ruang <span class="font-medium text-slate-800">{{ $classroom->room ?: '—' }}</span></span>
        <span class="text-slate-500">Kapasitas <span class="font-medium text-slate-800">{{ $classroom->capacity ?: '—' }}</span></span>
        <span class="text-slate-500">Sisa kursi
            <span class="font-medium {{ $classroom->remainingCapacity() === 0 ? 'text-amber-700' : 'text-slate-800' }}">
                {{ $classroom->remainingCapacity() ?? '—' }}
            </span>
        </span>
        <x-status-badge :status="$classroom->status" class="ml-auto" />
    </div>
</div>

{{-- ---------------------------------------------------------------- --}}
{{-- Tabs — only those the viewer may actually open                    --}}
{{-- ---------------------------------------------------------------- --}}
@php
    $tabs = [
        'siswa'    => ['label' => 'Siswa', 'show' => true],
        'absensi'  => ['label' => 'Absensi', 'show' => auth()->user()->can('viewAttendance', $classroom)],
        'ortu'     => ['label' => 'Orang Tua/Wali', 'show' => auth()->user()->can('viewParents', $classroom)],
        'pengumuman' => ['label' => 'Pengumuman', 'show' => auth()->user()->can('viewAnnouncements', $classroom)],
        'laporan'  => ['label' => 'Laporan', 'show' => auth()->user()->can('viewReports', $classroom)],
        'wali-kelas' => ['label' => 'Wali Kelas', 'show' => auth()->user()->can('classroom.view')],
    ];
@endphp

<div class="mb-4 -mx-4 overflow-x-auto px-4 sm:mx-0 sm:px-0">
    <div class="inline-flex min-w-full gap-1 rounded-lg bg-slate-100 p-1" role="tablist">
        @foreach ($tabs as $key => $tab)
            @if ($tab['show'])
                {{-- The tab is a hash anchor, so the server cannot know which is
                     active; the highlight is applied client-side by :target. --}}
                <a href="#{{ $key }}"
                   class="whitespace-nowrap rounded-md px-3.5 py-2 text-sm font-medium text-slate-600 transition hover:bg-white hover:text-slate-900 target:bg-white target:text-slate-900 target:shadow-sm">
                    {{ $tab['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</div>

{{-- ---------------------------------------------------------------- --}}
{{-- SISWA                                                             --}}
{{-- ---------------------------------------------------------------- --}}
<section id="siswa" class="mb-8 scroll-mt-24">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-base font-semibold text-slate-900">Siswa ({{ $enrollments->count() }})</h2>
        <form method="GET" class="flex gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama atau NISN"
                   class="rounded-lg border-slate-300 py-1.5 pl-3 pr-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        </form>
    </div>

    <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:block">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">NISN</th>
                    <th class="px-4 py-3 font-semibold">Nama</th>
                    <th class="px-4 py-3 font-semibold">Kelengkapan</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 text-right font-semibold">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($enrollments as $enrollment)
                    @php
                        $student = $enrollment->student;
                        $reg = $student?->registration;
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $student?->nisn }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $student?->full_name }}</td>
                        <td class="px-4 py-3">
                            @if ($reg)
                                <x-status-badge :status="$reg->status" />
                                <span class="ml-1.5 text-xs tabular-nums text-slate-500">{{ $reg->completeness }}%</span>
                            @else
                                <span class="text-xs text-slate-400">Belum mendaftar</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$enrollment->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('moveStudent', $classroom)
                                <a href="{{ route('academic.enrollments.move', $student) }}"
                                   class="rounded-md bg-slate-100 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                                    Pindahkan
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                            Belum ada siswa di kelas ini.
                            @can('manageStudents', $classroom)
                                <a href="{{ route('academic.enrollments.create', ['tahun' => $classroom->academic_year_id, 'kelas' => $classroom->id]) }}"
                                   class="ml-1 font-medium text-indigo-600 hover:underline">Tempatkan siswa</a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="space-y-3 sm:hidden">
        @forelse ($enrollments as $enrollment)
            @php $student = $enrollment->student; $reg = $student?->registration; @endphp
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-slate-900">{{ $student?->full_name }}</p>
                        <p class="font-mono text-xs text-slate-500">{{ $student?->nisn }}</p>
                    </div>
                    <x-status-badge :status="$enrollment->status" />
                </div>

                <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Data</dt>
                        <dd class="font-medium text-slate-800">
                            {{ $reg ? $reg->completeness.'%' : 'Belum mendaftar' }}
                        </dd>
                    </div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <dt class="text-xs text-slate-500">Status Pendaftaran</dt>
                        <dd class="font-medium text-slate-800">{{ $reg?->status === 'verified' ? 'Terverifikasi' : ($reg?->status_label ?? '—') }}</dd>
                    </div>
                </dl>

                @can('moveStudent', $classroom)
                    <a href="{{ route('academic.enrollments.move', $student) }}"
                       class="mt-3 block w-full rounded-lg bg-slate-100 px-3 py-2 text-center text-sm font-medium text-slate-700">
                        Pindahkan Kelas
                    </a>
                @endcan
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                Belum ada siswa di kelas ini.
            </p>
        @endforelse
    </div>
</section>

{{-- ---------------------------------------------------------------- --}}
{{-- ABSENSI                                                           --}}
{{-- ---------------------------------------------------------------- --}}
@if (auth()->user()->can('viewAttendance', $classroom))
    <section id="absensi" class="mb-8 scroll-mt-24">
        <h2 class="mb-3 text-base font-semibold text-slate-900">Absensi Hari Ini</h2>

        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            @if ($todaySession && $todayRecords->isNotEmpty())
                <div class="mb-3 flex flex-wrap gap-2 text-sm">
                    @foreach ($attendanceStatus as $value => $label)
                        @php $n = $todayRecords->where('status', $value)->count(); @endphp
                        <span class="rounded-md bg-slate-50 px-2.5 py-1 text-slate-600">
                            {{ $label }} <span class="font-semibold tabular-nums text-slate-900">{{ $n }}</span>
                        </span>
                    @endforeach
                </div>
                <a href="{{ route('academic.attendance.show', $classroom) }}"
                   class="inline-block rounded-lg bg-slate-900 px-3.5 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Ubah / Lihat Detail
                </a>
            @else
                <p class="text-sm text-slate-500">Absensi hari ini belum diisi.</p>
                @can('manageAttendance', $classroom)
                    <a href="{{ route('academic.attendance.show', $classroom) }}"
                       class="mt-3 inline-block rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Isi Absensi Sekarang
                    </a>
                @endcan
            @endif
        </div>
    </section>
@endif

{{-- ---------------------------------------------------------------- --}}
{{-- ORANG TUA / WALI                                                   --}}
{{-- ---------------------------------------------------------------- --}}
@if (auth()->user()->can('viewParents', $classroom))
    <section id="ortu" class="mb-8 scroll-mt-24">
        <h2 class="mb-3 text-base font-semibold text-slate-900">Orang Tua/Wali</h2>

        <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm sm:block">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Siswa</th>
                        <th class="px-4 py-3 font-semibold">Nama Wali</th>
                        <th class="px-4 py-3 font-semibold">Hubungan</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php $anyParent = false; @endphp
                    @foreach ($enrollments as $enrollment)
                        @foreach ($enrollment->student?->parents ?? [] as $parent)
                            @php $anyParent = true; @endphp
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium text-slate-900">{{ $enrollment->student?->full_name }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $parent->full_name }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $parent->relationLabel() }}</td>
                                <td class="px-4 py-3 tabular-nums text-slate-700">{{ $parent->phone ?: '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    @unless ($anyParent)
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">Belum ada data orang tua/wali.</td>
                        </tr>
                    @endunless
                </tbody>
            </table>
        </div>

        <div class="space-y-3 sm:hidden">
            @php $anyParent = false; @endphp
            @foreach ($enrollments as $enrollment)
                @foreach ($enrollment->student?->parents ?? [] as $parent)
                    @php $anyParent = true; @endphp
                    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                        <p class="text-xs text-slate-500">{{ $enrollment->student?->full_name }}</p>
                        <p class="mt-1 font-semibold text-slate-900">{{ $parent->full_name }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ $parent->relationLabel() }}
                            @if ($parent->phone) \\u00b7 <span class="tabular-nums">{{ $parent->phone }}</span> @endif
                        </p>
                    </div>
                @endforeach
            @endforeach
            @unless ($anyParent)
                <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                    Belum ada data orang tua/wali.
                </p>
            @endunless
        </div>
    </section>
@endif

{{-- ---------------------------------------------------------------- --}}
{{-- PENGUMUMAN                                                        --}}
{{-- ---------------------------------------------------------------- --}}
@if (auth()->user()->can('viewAnnouncements', $classroom))
    <section id="pengumuman" class="mb-8 scroll-mt-24">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-base font-semibold text-slate-900">Pengumuman</h2>
            @can('publishAnnouncement', $classroom)
                <a href="{{ route('academic.announcements.create', $classroom) }}"
                   class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-200">
                    + Pengumuman
                </a>
            @endcan
        </div>

        <div class="space-y-3">
            @forelse ($announcements as $a)
                <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">{{ $a->title }}</h3>
                        <x-status-badge :status="$a->audience === 'both' ? 'verified' : 'submitted'" />
                    </div>
                    <p class="mt-1.5 whitespace-pre-line text-sm text-slate-600">{{ $a->body }}</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ $a->published_at?->format('d M Y H:i') ?? 'Belum dipublikasikan' }}
                    </p>
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">
                    Belum ada pengumuman.
                </p>
            @endforelse
        </div>
    </section>
@endif

{{-- ---------------------------------------------------------------- --}}
{{-- LAPORAN                                                           --}}
{{-- ---------------------------------------------------------------- --}}
@if (auth()->user()->can('viewReports', $classroom))
    <section id="laporan" class="mb-8 scroll-mt-24">
        <h2 class="mb-3 text-base font-semibold text-slate-900">Laporan</h2>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-600">
                Ekspor daftar siswa, data orang tua/wali, dan rekap absensi kelas ini.
            </p>
            <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Ekspor PDF/Excel untuk laporan kelas sedang disiapkan pada tahap berikutnya.
            </p>
        </div>
    </section>
@endif

{{-- ---------------------------------------------------------------- --}}
{{-- WALI KELAS                                                        --}}
{{-- ---------------------------------------------------------------- --}}
<section id="wali-kelas" class="scroll-mt-24">
    <h2 class="mb-3 text-base font-semibold text-slate-900">Wali Kelas</h2>

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm text-slate-600">
            @if ($homeroom)
                <span class="font-medium text-slate-900">{{ $homeroom->name }}</span> \\u2014 wali kelas aktif
                sejak {{ $classroom->homeroomAssignments->firstWhere('status', 'active')?->started_at?->format('d M Y') ?? '—' }}.
            @else
                Belum ada wali kelas yang ditetapkan untuk kelas ini.
            @endif
        </p>

        @can('assignHomeroom', $classroom)
            <form method="POST" action="{{ route('academic.classes.homeroom', $classroom) }}"
                  class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto] sm:items-end">
                @csrf
                <div>
                    <label for="user_id" class="mb-1 block text-sm font-medium text-slate-700">
                        {{ $homeroom ? 'Ganti Wali Kelas' : 'Tetapkan Wali Kelas' }}
                    </label>
                    <select id="user_id" name="user_id" required
                            class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">\\u2014 Pilih pengguna</option>
                        @foreach ($homeroomCandidates as $teacher)
                            <option value="{{ $teacher->id }}" @selected($homeroom?->id === $teacher->id)>{{ $teacher->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="started_at" class="mb-1 block text-sm font-medium text-slate-700">Efektif</label>
                    <input id="started_at" type="date" name="started_at" value="{{ now()->toDateString() }}"
                           class="rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-2">
                    <input type="text" name="notes" placeholder="Catatan (opsional)"
                           class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-2">
                    <button class="w-full rounded-lg bg-indigo-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-indigo-700 sm:w-auto">
                        Simpan Penugasan
                    </button>
                    <p class="mt-1.5 text-xs text-slate-500">
                        Penugasan sebelumnya dicatat sebagai riwayat, tidak dihapus.
                    </p>
                </div>
            </form>
        @endcan
    </div>
</section>
@endsection
