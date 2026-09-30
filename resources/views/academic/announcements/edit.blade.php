@extends('components.app-shell')

@section('title', 'Ubah Pengumuman')
@section('page-title', 'Ubah Pengumuman')
@section('page-description', $classroom->name.' · '.$classroom->academicYear?->name)

@section('content')
<div class="mx-auto max-w-2xl">
    {{-- The class an announcement belongs to is fixed: the route carries it and
         the controller never reads classroom_id from the payload. --}}
    <p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
        Pengumuman ini milik kelas <span class="font-medium text-slate-800">{{ $classroom->name }}</span>
        dan tidak dapat dipindahkan ke kelas lain.
    </p>

    @include('academic.announcements.partial', [
        'submitAction' => route('academic.announcements.update', [$classroom, $announcement]),
        'submitLabel' => 'Simpan Perubahan',
        'method' => 'PUT',
    ])
</div>
@endsection
