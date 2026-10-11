@extends('components.app-shell')

@section('title', 'Workspace · Trash')
@section('page-title', 'Trash Drive')
@section('page-description', 'Pulihkan berkas dan folder yang dipindahkan dari Drive. Berkas di Trash tetap memakai ruang penyimpanan.')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if (session('success'))
        <x-alert variant="success" :message="session('success')" />
    @endif
    @error('folder')
        <p class="text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
    @enderror

    <nav aria-label="Navigasi Drive">
        <a class="btn btn-secondary" href="{{ route('workspace.drive.index') }}">Kembali ke Drive Saya</a>
    </nav>

    <section class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]" aria-labelledby="trash-items-title">
        <div class="border-b border-[var(--app-border)] px-5 py-4 sm:px-6">
            <h2 id="trash-items-title" class="text-h3 font-semibold text-[var(--app-text)]">Item di Trash</h2>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Versi dan bytes privat tetap disimpan. Folder hanya dapat dibuang jika kosong.</p>
        </div>

        @if ($folders->isEmpty() && $files->isEmpty())
            <div class="px-5 py-12 text-center sm:px-6">
                <x-icon name="trash" class="mx-auto size-10 text-[var(--app-text-subtle)]" aria-hidden="true" />
                <p class="mt-3 text-body font-medium text-[var(--app-text)]">Trash kosong</p>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">Item yang Anda buang akan muncul di sini untuk dipulihkan.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-small">
                    <caption class="sr-only">Item Trash Drive pribadi Anda</caption>
                    <thead class="bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Nama</th>
                            <th scope="col" class="px-5 py-3 font-medium">Jenis</th>
                            <th scope="col" class="px-5 py-3 font-medium">Dihapus</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium sm:px-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--app-border)]">
                        @foreach ($folders as $folder)
                            <tr>
                                <th scope="row" class="px-5 py-4 font-medium text-[var(--app-text)] sm:px-6">{{ $folder->name }}</th>
                                <td class="px-5 py-4 text-[var(--app-text-muted)]">Folder kosong</td>
                                <td class="px-5 py-4 text-[var(--app-text-muted)]">{{ $folder->deleted_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4 text-right sm:px-6">
                                    <form method="POST" action="{{ route('workspace.drive.folders.restore', $folder->public_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary min-h-10" aria-label="Pulihkan folder {{ $folder->name }}">Pulihkan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($files as $file)
                            <tr>
                                <th scope="row" class="px-5 py-4 font-medium text-[var(--app-text)] sm:px-6">
                                    {{ $file->name }}
                                    <span class="mt-1 block text-xs font-normal text-[var(--app-text-muted)]">{{ $file->humanSize() }} · {{ $file->versions->count() }} versi</span>
                                    @if ($file->folder)
                                        <span class="mt-1 block text-xs font-normal text-[var(--app-text-muted)]">Lokasi asal: {{ $file->folder->name }}</span>
                                    @else
                                        <span class="mt-1 block text-xs font-normal text-[var(--app-text-muted)]">Lokasi asal: Drive Saya (root)</span>
                                    @endif
                                </th>
                                <td class="px-5 py-4 text-[var(--app-text-muted)]">{{ $file->mime_type }}</td>
                                <td class="px-5 py-4 text-[var(--app-text-muted)]">{{ $file->deleted_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-5 py-4 text-right sm:px-6">
                                    <form method="POST" action="{{ route('workspace.drive.files.restore', $file->public_id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-secondary min-h-10" aria-label="Pulihkan berkas {{ $file->name }}">Pulihkan</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
