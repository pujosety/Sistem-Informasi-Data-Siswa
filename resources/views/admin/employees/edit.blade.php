@extends('components.app-shell')

@section('title', 'Ubah Kepegawaian')
@section('page-title', 'Ubah Kepegawaian')
@section('page-description', $employee->displayName())

@section('page-actions')
    <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Detail
    </a>
@endsection

@section('content')
    @include('admin.employees._form')
@endsection
