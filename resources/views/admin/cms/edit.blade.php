@extends('components.app-shell')

@section('title', 'Ubah Konten')
@section('page-title', 'Ubah Konten')
@section('page-description', $post->title)

@section('page-actions')
    <a href="{{ route('admin.cms.show', $post) }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Detail
    </a>
@endsection

@section('content')
    @include('admin.cms._form')
@endsection
