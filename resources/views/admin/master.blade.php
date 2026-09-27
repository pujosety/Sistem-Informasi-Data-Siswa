@extends('components.app-shell')

@section('title', 'Master Data')
@section('heading', 'Master Data')

@section('content')
<div class="grid lg:grid-cols-2 gap-4">
    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Tahun Ajaran</h3>
        <ul class="text-sm space-y-1 mb-4">
            @foreach ($years as $y)
                <li class="flex justify-between border-b last:border-0 py-1">
                    <span>{{ $y->name }}</span>
                    <span class="text-xs {{ $y->is_active ? 'text-emerald-600' : 'text-slate-400' }}">{{ $y->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.master.years') }}" class="space-y-2">
            @csrf
            <input class="input text-sm" name="name" placeholder="2027/2028" required>
            <div class="grid grid-cols-2 gap-2">
                <input class="input text-sm" type="date" name="start_date" required>
                <input class="input text-sm" type="date" name="end_date" required>
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1"> Jadikan aktif</label>
            <button class="btn btn-primary text-xs" type="submit">Tambah</button>
        </form>
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Jurusan</h3>
        <ul class="text-sm space-y-1 mb-4">
            @foreach ($departments as $d)
                <li class="flex justify-between border-b last:border-0 py-1"><span>{{ $d->name }}</span><span class="text-xs text-slate-400">{{ $d->code }}</span></li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.master.departments') }}" class="space-y-2">
            @csrf
            <input class="input text-sm" name="name" placeholder="Nama jurusan" required>
            <input class="input text-sm" name="code" placeholder="Kode (IPA)" required>
            <button class="btn btn-primary text-xs" type="submit">Tambah</button>
        </form>
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Kelas</h3>
        <div class="overflow-x-auto mb-4">
            <table class="w-full text-xs">
                <thead class="text-left text-slate-500 border-b"><tr><th class="py-1">Kelas</th><th>Jurusan</th><th>TA</th></tr></thead>
                <tbody>
                    @foreach ($classes as $c)
                        <tr class="border-b last:border-0">
                            <td class="py-1">{{ $c->name }}</td><td class="text-slate-500">{{ $c->department?->code }}</td><td class="text-slate-500">{{ $c->academicYear?->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.master.classes') }}" class="space-y-2">
            @csrf
            <select class="input text-sm" name="academic_year_id" required>
                @foreach ($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
            </select>
            <select class="input text-sm" name="department_id">
                <option value="">Tanpa jurusan</option>
                @foreach ($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
            </select>
            <div class="grid grid-cols-3 gap-2">
                <input class="input text-sm" name="name" placeholder="X IPA 1" required>
                <input class="input text-sm" name="level" placeholder="X" required>
                <input class="input text-sm" type="number" name="capacity" placeholder="36">
            </div>
            <button class="btn btn-primary text-xs" type="submit">Tambah Kelas</button>
        </form>
    </div>

    <div class="card p-5">
        <h3 class="font-semibold text-slate-800 mb-3">Jenis Dokumen</h3>
        <ul class="text-sm space-y-1 mb-4">
            @foreach ($documentTypes as $d)
                <li class="flex justify-between border-b last:border-0 py-1">
                    <span>{{ $d->name }}</span>
                    <span class="text-xs text-slate-400">{{ $d->is_required ? 'Wajib' : 'Opsional' }}</span>
                </li>
            @endforeach
        </ul>
        <form method="POST" action="{{ route('admin.master.document-types') }}" class="space-y-2">
            @csrf
            <input class="input text-sm" name="name" placeholder="Nama dokumen" required>
            <input class="input text-sm" name="label" placeholder="Label tampilan" required>
            <input class="input text-sm" type="number" name="max_size_kb" value="2048" required>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_required" value="1" checked> Wajib diunggah</label>
            <button class="btn btn-primary text-xs" type="submit">Tambah</button>
        </form>
    </div>
</div>
@endsection
