@extends('components.app-shell')

@section('title', 'Tambah Kepegawaian')
@section('page-title', 'Tambah Kepegawaian')
@section('page-description', 'Catat hubungan kerja staff yang sudah ada')

@section('page-actions')
    <a href="{{ route('admin.employees') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')
    @include('admin.employees._form')
@endsection
