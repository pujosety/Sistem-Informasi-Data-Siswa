@props(['file', 'version'])
@php
    $state = app(\App\Services\WorkspaceMalwareScanService::class)->status($version);
    $labels = ['clean' => 'Bersih', 'infected' => 'Dikarantina: ancaman terdeteksi', 'pending' => 'Belum diperiksa — akses ditahan', 'unavailable' => 'Pemeriksaan gagal — akses ditahan'];
@endphp
<div class="text-xs space-y-1" role="status">
    <span>{{ $labels[$state] ?? $labels['unavailable'] }}</span>
    @if($state !== 'clean' && auth()->id() === $file->owner_id && ! $file->trashed())
        <form method="POST" action="{{ route('workspace.drive.scans.retry', [$file, $version->id]) }}">
            @csrf
            <button type="submit" class="btn btn-ghost min-h-11">Ulangi pemeriksaan versi {{ $version->version_number }}</button>
        </form>
    @endif
</div>
