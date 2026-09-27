@extends('components.app-shell')

@section('title', 'Statistik')
@section('heading', 'Statistik Siswa')

@section('content')
<div class="grid lg:grid-cols-2 gap-4">
    @foreach ([
        ['Siswa per Kelas', $byClass, 'class'],
        ['Siswa per Jurusan', $byDepartment, 'department'],
        ['Jenis Kelamin', ['Laki-laki' => $byGender['L'] ?? 0, 'Perempuan' => $byGender['P'] ?? 0], null],
        ['Tahun Masuk', collect($byEntryYear)->map(fn ($v) => ['total' => $v])->values()->toArray(), null],
    ] as [$title, $rows, $key])
        <div class="card p-5">
            <h3 class="font-semibold text-slate-800 mb-3">{{ $title }}</h3>
            <ul class="space-y-2 text-sm">
                @php $max = max(1, max(collect($rows)->map(fn ($r) => is_array($r) ? ($r['total'] ?? 0) : $r)->all() ?: [1])); @endphp
                @forelse ($rows as $name => $row)
                    @php $value = is_array($row) ? ($row['total'] ?? 0) : $row; @endphp
                    <li>
                        <div class="flex justify-between mb-1">
                            <span class="text-slate-600">{{ is_array($name) ? ($name['class'] ?? $name['department'] ?? '') : $name }}</span>
                            <span class="font-semibold">{{ $value }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-slate-200 overflow-hidden">
                            <div class="h-full bg-blue-600" style="width: {{ $value / $max * 100 }}%"></div>
                        </div>
                    </li>
                @empty
                    <li class="text-slate-500">Belum ada data.</li>
                @endforelse
            </ul>
        </div>
    @endforeach
</div>
@endsection
