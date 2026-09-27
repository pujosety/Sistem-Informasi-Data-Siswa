@extends('components.app-shell')

@section('title', 'Pratinjau Laporan')
@section('heading', $title)
@section('subheading', $students->count().' baris data')

@section('content')
<div class="mb-4 flex gap-2">
    <a class="btn btn-secondary" href="{{ route('laporan.excel', request()->query()) }}">Excel</a>
    <a class="btn btn-secondary" href="{{ route('laporan.csv', request()->query()) }}">CSV</a>
    <a class="btn btn-secondary" href="{{ route('laporan.pdf', request()->query()) }}">PDF</a>
    <a class="btn btn-secondary" href="{{ route('laporan.index') }}">Ubah Filter</a>
</div>

<div class="card overflow-x-auto">
    <table class="w-full text-xs">
        <thead class="bg-slate-50 text-left text-slate-600 border-b">
            <tr>
                <th class="px-3 py-2">NISN</th><th class="px-3 py-2">Nama</th><th class="px-3 py-2">L/P</th>
                <th class="px-3 py-2">Angkatan</th><th class="px-3 py-2">Kelas</th><th class="px-3 py-2">Jurusan</th>
                <th class="px-3 py-2">Kelengkapan</th><th class="px-3 py-2">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($students as $s)
                <tr class="border-b last:border-0">
                    <td class="px-3 py-2">{{ $s->nisn }}</td>
                    <td class="px-3 py-2 font-medium">{{ $s->full_name }}</td>
                    <td class="px-3 py-2">{{ $s->genderLabel() }}</td>
                    <td class="px-3 py-2">{{ $s->entry_year ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $s->schoolClass?->name ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $s->schoolClass?->department?->name ?? '-' }}</td>
                    <td class="px-3 py-2">{{ $s->registration?->completeness ?? 0 }}%</td>
                    <td class="px-3 py-2">{{ $s->registration?->statusLabel() ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-3 py-8 text-center text-slate-500">Tidak ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
