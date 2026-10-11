@props(['folder' => null])
@php
    $limits = app(\App\Services\WorkspaceResumableUploadService::class)->limits();
    $scope = auth()->id().':'.app(\App\Services\SchoolContext::class)->id().':'.($folder ?? 'root');
@endphp
<section data-workspace-resumable-upload
    data-endpoint="{{ route('workspace.drive.uploads.create') }}"
    data-folder="{{ $folder ?? '' }}" data-scope="{{ $scope }}"
    data-max-bytes="{{ $limits['max_bytes'] }}"
    class="rounded-xl border border-slate-200 p-4 space-y-3">
    <h3 class="font-semibold">Unggahan besar yang dapat dilanjutkan</h3>
    <p class="text-sm text-slate-600">Maksimum {{ number_format($limits['max_bytes'] / 1048576, 1) }} MiB.
        Saat melanjutkan setelah halaman ditutup, pilih kembali berkas yang sama.
        Sesi kedaluwarsa setelah {{ config('workspace.uploads.ttl_minutes', 1440) }} menit.</p>
    @csrf
    <label class="block text-sm">Berkas
        <input data-upload-file type="file" class="block w-full mt-1" />
    </label>
    <div class="flex flex-wrap gap-2">
        <button data-upload-start type="button" class="px-3 py-2 rounded bg-indigo-600 text-white">Unggah / Lanjutkan</button>
        <button data-upload-pause type="button" disabled class="px-3 py-2 rounded border disabled:opacity-50">Jeda</button>
        <button data-upload-cancel type="button" disabled class="px-3 py-2 rounded border disabled:opacity-50">Batalkan</button>
    </div>
    <progress data-upload-progress max="100" value="0" aria-label="Progres unggahan" class="w-full"></progress>
    <p data-upload-status role="status" aria-live="polite" class="text-sm">Pilih berkas untuk mulai.</p>
    <noscript>Unggahan yang dapat dilanjutkan membutuhkan JavaScript. Gunakan unggahan biasa untuk berkas kecil.</noscript>
</section>
