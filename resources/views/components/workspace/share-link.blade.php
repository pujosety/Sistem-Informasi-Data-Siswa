@props(['file'])
@if (\Illuminate\Support\Facades\Route::has('workspace.shares.manage'))
    <a class="btn btn-secondary min-h-10" href="{{ route('workspace.shares.manage', $file->public_id) }}"
       aria-label="Kelola izin baca {{ $file->name }}">Bagikan (baca saja)</a>
@endif
