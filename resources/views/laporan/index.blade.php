@extends('components.app-shell')

@section('title', 'Laporan')
@section('heading', 'Laporan & Rekapitulasi')

@section('content')
<form method="GET" class="card p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
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
    <select class="input" name="entry_year">
        <option value="">Semua angkatan</option>
        @foreach ($entryYears as $year)
            <option value="{{ $year }}" @selected(request('entry_year') === $year)>{{ $year }}</option>
        @endforeach
    </select>
    <select class="input" name="status">
        <option value="">Semua status</option>
        @foreach (\App\Models\Registration::STATUS_LABELS as $value => $label)
            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <div class="sm:col-span-2 lg:col-span-5 flex flex-wrap gap-2">
        <button class="btn btn-primary" type="submit">Terapkan Filter</button>
        <a class="btn btn-secondary" href="{{ route('laporan.preview', request()->query()) }}">Pratinjau</a>
        <a class="btn btn-secondary" href="{{ route('laporan.excel', request()->query()) }}">Excel</a>
        <a class="btn btn-secondary" href="{{ route('laporan.csv', request()->query()) }}">CSV</a>
        <a class="btn btn-secondary" href="{{ route('laporan.pdf', request()->query()) }}">PDF</a>
    </div>
</form>

<div class="grid grid-cols-2 gap-4">
    <div class="card p-5">
        <p class="text-xs text-slate-500">Total data terpilih</p>
        <p class="text-3xl font-bold mt-1">{{ number_format($total) }}</p>
    </div>
    <div class="card p-5">
        <p class="text-xs text-slate-500">Sudah terverifikasi</p>
        <p class="text-3xl font-bold mt-1 text-emerald-600">{{ number_format($verified) }}</p>
    </div>
</div>
@endsection
