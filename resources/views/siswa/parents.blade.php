@extends('components.app-shell')

@section('title', 'Data Orang Tua')
@section('heading', 'Data Orang Tua / Wali')

@section('content')
@php $parents = $student->parents->keyBy('relation'); @endphp

<form method="POST" action="{{ route('siswa.parents.update') }}" class="space-y-4">
    @csrf
    @method('PUT')

    @foreach ([['father', 'Ayah', 'father_'], ['mother', 'Ibu', 'mother_']] as [$key, $label, $prefix])
        <div class="card p-5">
            <h2 class="font-semibold text-slate-800 mb-4">Data {{ $label }}</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div><label class="label">Nama Lengkap {{ $label }} *</label>
                    <input class="input" name="{{ $prefix }}name" value="{{ old($prefix.'name', $parents[$key]->full_name ?? '') }}" required></div>
                <div><label class="label">Pekerjaan</label>
                    <input class="input" name="{{ $prefix }}job" value="{{ old($prefix.'job', $parents[$key]->job ?? '') }}"></div>
                <div><label class="label">Nomor Telepon *</label>
                    <input class="input" name="{{ $prefix }}phone" value="{{ old($prefix.'phone', $parents[$key]->phone ?? '') }}" required></div>
                <div><label class="label">NIK</label>
                    <input class="input" name="{{ $prefix }}nik" value="{{ old($prefix.'nik', $parents[$key]->nik ?? '') }}" maxlength="16"></div>
            </div>
        </div>
    @endforeach

    <div class="card p-5">
        <h2 class="font-semibold text-slate-800 mb-4">Data Wali <span class="text-sm font-normal text-slate-500">(opsional)</span></h2>
        <div class="grid md:grid-cols-2 gap-4">
            <div><label class="label">Nama Lengkap Wali</label>
                <input class="input" name="guardian_name" value="{{ old('guardian_name', $parents['guardian']->full_name ?? '') }}"></div>
            <div><label class="label">Pekerjaan Wali</label>
                <input class="input" name="guardian_job" value="{{ old('guardian_job', $parents['guardian']->job ?? '') }}"></div>
            <div><label class="label">Nomor Telepon Wali</label>
                <input class="input" name="guardian_phone" value="{{ old('guardian_phone', $parents['guardian']->phone ?? '') }}"></div>
        </div>
        <div class="mt-4"><label class="label">Alamat Orang Tua / Wali</label>
            <textarea class="input" name="parent_address" rows="2">{{ old('parent_address', $parents['father']->address ?? '') }}</textarea></div>
    </div>

    <button class="btn btn-primary" type="submit">Simpan Data Orang Tua</button>
</form>
@endsection
