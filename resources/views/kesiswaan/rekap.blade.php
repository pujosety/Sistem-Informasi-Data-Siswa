@extends('components.app-shell')

@section('title', 'Rekapitulasi')
@section('heading', 'Rekapitulasi Data Siswa')

@section('content')
<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3">
    <select class="input" name="academic_year_id">
        <option value="">Semua tahun ajaran</option>
        @foreach ($options['years'] as $id => $name)
            <option value="{{ $id }}" @selected((string) request('academic_year_id') === (string) $id)>{{ $name }}</option>
        @endforeach
    </select>
    <select class="input" name="class_id">
        <option value="">Semua kelas</option>
        @foreach ($options['classes'] as $id => $name)
            <option value="{{ $id }}" @selected((string) request('class_id') === (string) $id)>{{ $name }}</option>
        @endforeach
    </select>
    <select class="input" name="department_id">
        <option value="">Semua jurusan</option>
        @foreach ($options['departments'] as $id => $name)
            <option value="{{ $id }}" @selected((string) request('department_id') === (string) $id)>{{ $name }}</option>
        @endforeach
    </select>
    <select class="input" name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Registration::STATUS_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <select class="input" name="gender">
        <option value="">Semua</option>
        <option value="L" @selected(request('gender') === 'L')>Laki-laki</option>
        <option value="P" @selected(request('gender') === 'P')>Perempuan</option>
    </select>
    <input class="input" name="q" value="{{ request('q') }}" placeholder="Cari nama/NISN">
    <div class="sm:col-span-2 lg:col-span-6 flex gap-2">
        <button class="btn btn-primary" type="submit">Terapkan Filter</button>
        <a class="btn btn-secondary" href="{{ route('laporan.excel', request()->query()) }}">Export Excel</a>
        <a class="btn btn-secondary" href="{{ route('laporan.pdf', request()->query()) }}">Export PDF</a>
    </div>
</form>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
    <div class="card p-4"><p class="text-xs text-slate-500">Total</p><p class="text-2xl font-bold">{{ $total }}</p></div>
    <div class="card p-4"><p class="text-xs text-slate-500">Per Kelas</p><p class="text-2xl font-bold">{{ $byClass->count() }}</p></div>
    <div class="card p-4"><p class="text-xs text-slate-500">Per Status</p><p class="text-2xl font-bold">{{ $byStatus->count() }}</p></div>
    <div class="card p-4"><p class="text-xs text-slate-500">L/P</p><p class="text-2xl font-bold">{{ $byGender->count() }}</p></div>
</div>

<div class="grid lg:grid-cols-3 gap-4">
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Per Kelas</h3>
        <ul class="text-sm space-y-1.5">
            @forelse ($byClass as $name => $count)
                <li class="flex justify-between"><span class="text-slate-600">{{ $name }}</span><span class="font-semibold">{{ $count }}</span></li>
            @empty <li class="text-slate-500">-</li> @endforelse
        </ul>
    </div>
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Per Status</h3>
        <ul class="text-sm space-y-1.5">
            @forelse ($byStatus as $status => $count)
                <li class="flex justify-between"><span class="text-slate-600">{{ \App\Models\Registration::STATUS_LABELS[$status] ?? $status }}</span><span class="font-semibold">{{ $count }}</span></li>
            @empty <li class="text-slate-500">-</li> @endforelse
        </ul>
    </div>
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Jenis Kelamin</h3>
        <ul class="text-sm space-y-1.5">
            <li class="flex justify-between"><span class="text-slate-600">Laki-laki</span><span class="font-semibold">{{ $byGender['L'] ?? 0 }}</span></li>
            <li class="flex justify-between"><span class="text-slate-600">Perempuan</span><span class="font-semibold">{{ $byGender['P'] ?? 0 }}</span></li>
        </ul>
    </div>
</div>
@endsection
