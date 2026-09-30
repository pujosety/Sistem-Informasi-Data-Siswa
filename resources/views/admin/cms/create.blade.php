@extends('components.app-shell')

@section('title', 'Tulis Konten')
@section('page-title', 'Tulis Konten')
@section('page-description', 'Tersimpan sebagai draft sampai diterbitkan')

@section('page-actions')
    <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')
    @include('admin.cms._form')
@endsection
