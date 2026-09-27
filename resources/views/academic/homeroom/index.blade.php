@extends('components.app-shell')

@section('title', 'Kelas Saya')
@section('page-title', 'Kelas Saya')
@section('page-description', $year?->name . ' \u00b7 ' . $summaries->count() . ' kelas di tractor Anda')

@section('content')

@forelse ($summaries as $s)
    @php $c = $s['classroom']; @endphp

    <article class="mb-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3.5 sm:px-5">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $c->name }}</h2>
                <p class="mt-0.5 text-sm text-slate-600">
                    {{ $c->academicYear?->name }}
                    @if ($c->department) \u00b7 {{ $c->department->name }} @endif
                    @if ($c->room) \u00b7 Ruang {{ $c->room }} @endif
                </p>
            </div>
            <a href="{{ route('academic.classes.show', $c) }}"
               class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                Buka Kelas
            </a>
        </header>

        <div class="grid grid-cols-2 divide-slate-200 sm:grid-cols-4 sm:divide-x">
            <div class="px-4 py-3.5 sm:px-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Siswa</p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums text-slate-900">
                    {{ $s['students'] }}
                    @if ($c->capacity)
                        <span class="text-sm font-normal text-slate-400">/ {{ $c->capacity }}</span>
                    @endif
                </p>
            </div>

            <div class="border-t border-slate-200 px-4 py-3.5 sm:border-t-0 sm:px-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Kehadiran Hari Ini</p>
                @if ($s['present_today'] === null)
                    <p class="mt-0.5 text-sm font-semibold text-amber-700">Belum diisi</p>
                @else
                    <p class="mt-0.5 text-xl font-semibold tabular-nums text-slate-900">
                        {{ $s['present_today'] }}<span class="text-sm font-normal text-slate-400">/ {{ $s['students'] }}</span>
                    </p>
                @endif
            </div>

            <div class="border-t border-slate-200 px-4 py-3.5 sm:border-t-0 sm:px-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Data Belum Lengkap</p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums {{ $s['incomplete'] > 0 ? 'text-amber-600' : 'text-slate-900' }}">
                    {{ $s['incomplete'] }}
                </p>
            </div>

            <div class="border-t border-slate-200 px-4 py-3.5 sm:border-t-0 sm:px-5">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Pengumuman Aktif</p>
                <p class="mt-0.5 text-xl font-semibold tabular-nums text-slate-900">{{ $s['announcements'] }}</p>
            </div>
        </div>

        @can('viewAttendance', $c)
            <footer class="flex flex-wrap gap-2 border-t border-slate-200 px-4 py-3 sm:px-5">
                <a href="{{ route('academic.attendance.show', $c) }}"
                   class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Isi Absensi
                </a>
                <a href="{{ route('academic.classes.show', $c) }}#siswa"
                   class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                    Lihat Siswa
                </a>
                @can('viewParents', $c)
                    <a href="{{ route('academic.classes.show', $c) }}#orang-tua"
                       class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        Orang Tua/Wali
                    </a>
                @endcan
                @can('publishAnnouncement', $c)
                    <a href="{{ route('academic.announcements.create', $c) }}"
                       class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        Buat Pengumuman
                    </a>
                @endcan
                @can('viewReports', $c)
                    <a href="{{ route('academic.classes.show', $c) }}#laporan"
                       class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                        Cetak Laporan
                    </a>
                @endcan
            </footer>
        @endcan
    </article>
@empty
    <div class="rounded-xl border border-dashed border-slate-300 px-6 py-12 text-center">
        <p class="text-sm font-medium text-slate-700">Belum ada kelas yang ditugaskan kepada Anda.</p>
        <p class="mt-1 text-sm text-slate-500">
            Administrator perlu menetapkan Anda sebagai Wali Kelas terlebih dahulu.
        </p>
    </div>
@endforelse
@endsection
