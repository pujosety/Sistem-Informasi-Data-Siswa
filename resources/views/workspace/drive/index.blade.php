@extends('components.app-shell')

@section('title', 'Workspace · Drive Saya')
@section('page-title', 'Drive Saya')
@section('page-description', 'Simpan dan ambil berkas pribadi Anda dengan aman.')

@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    @if (session('success'))
        <x-alert variant="success" :message="session('success')" />
    @endif
    @error('destination')
        <p class="text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
    @enderror
    @error('folder')
        <p class="text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
    @enderror
    @error('version')
        <p class="text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
    @enderror

    <nav aria-label="Lokasi Drive">
        <ol class="flex flex-wrap items-center gap-2 text-small text-[var(--app-text-muted)]">
            <li><a class="underline-offset-4 hover:underline focus-visible:outline" href="{{ route('workspace.drive.index') }}">Drive Saya</a></li>
            @foreach ($breadcrumbs as $breadcrumb)
                <li aria-hidden="true">/</li>
                <li>
                    @if ($loop->last)
                        <span aria-current="page" class="font-medium text-[var(--app-text)]">{{ $breadcrumb->name }}</span>
                    @else
                        <a class="underline-offset-4 hover:underline focus-visible:outline"
                           href="{{ route('workspace.drive.index', ['folder' => $breadcrumb->public_id]) }}">{{ $breadcrumb->name }}</a>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <div class="flex gap-3">
        <a class="btn btn-secondary" href="{{ route('workspace.drive.index') }}">Semua berkas</a>
        <a class="btn btn-secondary" href="{{ route('workspace.drive.index', ['favorites' => 1, 'q' => $search ?: null]) }}" aria-current="{{ $favoritesOnly ? 'page' : 'false' }}">Favorit</a>
        <a class="btn btn-secondary" href="{{ route('workspace.drive.trash') }}">Trash</a>
        @if (\Illuminate\Support\Facades\Route::has('workspace.shares.received'))
            <a class="btn btn-secondary" href="{{ route('workspace.shares.received') }}">Dibagikan kepada saya</a>
        @endif
    </div>

    <form method="GET" action="{{ route('workspace.drive.index') }}" role="search" aria-label="Cari di Drive" class="flex flex-col gap-2 sm:flex-row">
        @if ($favoritesOnly)<input type="hidden" name="favorites" value="1">@endif
        <div class="min-w-0 flex-1">
            <label for="drive-search" class="sr-only">Cari nama berkas atau folder</label>
            <input id="drive-search" name="q" type="search" maxlength="100" value="{{ $search }}"
                   placeholder="Cari nama berkas atau folder"
                   class="field w-full" @error('q') aria-invalid="true" aria-describedby="drive-search-error" @enderror>
            @error('q')
                <p id="drive-search-error" class="mt-1 text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="btn btn-secondary min-h-11 shrink-0">Cari</button>
        @if ($search !== '')
            <a href="{{ route('workspace.drive.index', $favoritesOnly ? ['favorites' => 1] : []) }}" class="btn btn-ghost min-h-11 shrink-0">Hapus pencarian</a>
        @endif
    </form>

    <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-5 sm:p-6"
             aria-labelledby="drive-upload-title">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="drive-upload-title" class="text-h3 font-semibold text-[var(--app-text)]">Unggah berkas</h2>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">
                    Berkas bersifat privat; penerima yang Anda izinkan hanya dapat membaca. Maksimum {{ $maxFileKb }} KB per unggahan.
                </p>
            </div>
            @php($quotaBytes = app(\App\Services\WorkspaceDriveService::class)->quotaBytesFor(auth()->user()))
            <div class="text-small text-[var(--app-text-muted)]" aria-label="Kuota penyimpanan Drive">
                <p>Terpakai: {{ number_format($usedBytes) }} byte / Kuota: {{ number_format($quotaBytes) }} byte</p>
                <p>Sisa: {{ number_format(max(0, $quotaBytes - $usedBytes)) }} byte</p>
                <p class="mt-1">Semua versi dan berkas di Trash tetap dihitung. Kuota berlaku per akun di sekolah aktif.</p>
                @if ($usedBytes >= $quotaBytes)
                    <p class="mt-1 text-[var(--app-danger)]" role="status">Kuota penyimpanan habis. Berkas tersimpan tetap dapat diunduh.</p>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('workspace.drive.files.store') }}" enctype="multipart/form-data" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            @if ($currentFolder)
                <input type="hidden" name="folder" value="{{ $currentFolder->public_id }}">
            @endif
            <div class="min-w-0 flex-1">
                <label for="drive-file" class="label">Pilih berkas</label>
                <input id="drive-file" name="file" type="file" required aria-describedby="drive-file-help"
                       class="field w-full @error('file') field-error @enderror">
                <p id="drive-file-help" class="help-text mt-1">Jenis berkas diverifikasi dari kontennya, bukan nama ekstensi saja.</p>
                @error('file')
                    <p class="mt-1 text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary min-h-11 shrink-0">
                <x-icon name="upload" class="size-4" />
                Simpan ke Drive
            </button>
        </form>
    </section>

    <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-5 sm:p-6"
             aria-labelledby="drive-folder-title">
        <x-workspace.resumable-upload :folder="$currentFolder?->public_id" />
        <h2 id="drive-folder-title" class="text-h3 font-semibold text-[var(--app-text)]">Folder baru</h2>
        <p class="mt-1 text-small text-[var(--app-text-muted)]">Folder dan isinya hanya terlihat oleh akun Anda.</p>
        <form method="POST" action="{{ route('workspace.drive.folders.store') }}" class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            @if ($currentFolder)
                <input type="hidden" name="folder" value="{{ $currentFolder->public_id }}">
            @endif
            <div class="min-w-0 flex-1">
                <label for="drive-folder-name" class="label">Nama folder</label>
                <input id="drive-folder-name" name="name" type="text" required maxlength="180"
                       value="{{ old('name') }}" class="field w-full @error('name') field-error @enderror"
                       @error('name') aria-invalid="true" aria-describedby="drive-folder-error" @enderror>
                @error('name')
                    <p id="drive-folder-error" class="mt-1 text-small text-[var(--app-danger)]" role="alert">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn btn-secondary min-h-11 shrink-0">
                <x-icon name="folder-plus" class="size-4" />
                Buat folder
            </button>
        </form>
    </section>

    <section class="overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]"
             aria-labelledby="drive-files-title">
        <div class="border-b border-[var(--app-border)] px-5 py-4 sm:px-6">
            <h2 id="drive-files-title" class="text-h3 font-semibold text-[var(--app-text)]">
                {{ $favoritesOnly ? 'Favorit saya' : ($search !== '' ? 'Hasil pencarian: '.$search : ($currentFolder ? 'Isi folder: '.$currentFolder->name : 'Berkas dan folder saya')) }}
            </h2>
        </div>

        @if ($folders->isEmpty() && $files->isEmpty())
            <div class="px-5 py-12 text-center sm:px-6">
                <x-icon name="folder-open" class="mx-auto size-10 text-[var(--app-text-subtle)]" aria-hidden="true" />
                @if ($search !== '')
                    <p class="mt-3 text-body font-medium text-[var(--app-text)]">Tidak ada hasil untuk “{{ $search }}”</p>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">Coba kata kunci yang lain.</p>
                @else
                    <p class="mt-3 text-body font-medium text-[var(--app-text)]">Folder ini masih kosong</p>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">Buat folder atau unggah berkas untuk mulai mengisi Drive.</p>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-small">
                    <caption class="sr-only">Daftar berkas pribadi di Workspace Drive</caption>
                    <thead class="bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-medium sm:px-6">Nama</th>
                            <th scope="col" class="px-5 py-3 font-medium">Jenis</th>
                            <th scope="col" class="px-5 py-3 font-medium">Ukuran</th>
                            <th scope="col" class="px-5 py-3 text-right font-medium sm:px-6">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--app-border)]">
                        @foreach ($folders as $folder)
                            <tr>
                                <th scope="row" class="max-w-[22rem] truncate px-5 py-4 font-medium text-[var(--app-text)] sm:px-6">
                                    <a class="inline-flex items-center gap-2 underline-offset-4 hover:underline focus-visible:outline"
                                       href="{{ route('workspace.drive.index', ['folder' => $folder->public_id]) }}">
                                        <x-icon name="folder-open" class="size-4 shrink-0" aria-hidden="true" />
                                        {{ $folder->name }}
                                    </a>
                                </th>
                                <td class="whitespace-nowrap px-5 py-4 text-[var(--app-text-muted)]">Folder</td>
                                <td class="whitespace-nowrap px-5 py-4 text-[var(--app-text-muted)]">—</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <form method="POST" action="{{ route('workspace.drive.folders.rename', $folder) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        @if ($search)<input type="hidden" name="q" value="{{ $search }}">@endif
                                        @if ($favoritesOnly)<input type="hidden" name="favorites" value="1">@endif
                                        @if ($currentFolder)<input type="hidden" name="folder" value="{{ $currentFolder->public_id }}">@endif
                                        <label class="sr-only" for="rename-folder-{{ $folder->public_id }}">Nama baru untuk {{ $folder->name }}</label>
                                        <input id="rename-folder-{{ $folder->public_id }}" name="name" value="{{ $folder->name }}" maxlength="180" required class="field min-h-10 w-40">
                                        <button class="btn btn-secondary min-h-10" type="submit">Ganti nama</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.drive.folders.move', $folder->public_id) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <label class="sr-only" for="move-folder-{{ $folder->public_id }}">Lokasi baru untuk {{ $folder->name }}</label>
                                        <select id="move-folder-{{ $folder->public_id }}" name="destination" class="field min-h-10 w-40">
                                            <option value="root">Drive Saya (root)</option>
                                            @foreach ($moveDestinations as $destination)
                                                @if ($destination['id'] !== $folder->public_id)
                                                    <option value="{{ $destination['id'] }}">{{ $destination['label'] }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button class="btn btn-secondary min-h-10" type="submit">Pindahkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.drive.folders.trash', $folder) }}" class="mt-2">
                                        @csrf
                                        <button class="btn btn-ghost min-h-10" type="submit" aria-label="Pindahkan folder {{ $folder->name }} ke Trash">Pindahkan ke Trash</button>
                                    </form>
                                    <a href="{{ route('workspace.drive.index', ['folder' => $folder->public_id]) }}"
                                       class="btn btn-secondary min-h-10" aria-label="Buka folder {{ $folder->name }}">
                                        Buka
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @foreach ($files as $file)
                            <tr>
                                <th scope="row" class="max-w-[22rem] truncate px-5 py-4 font-medium text-[var(--app-text)] sm:px-6">
                                    {{ $file->name }}
                                    <x-workspace.share-link :file="$file" />
                                    @if ($search !== '' && $file->folder)
                                        <span class="mt-1 block text-xs font-normal text-[var(--app-text-muted)]">
                                            Lokasi:
                                            <a class="underline-offset-4 hover:underline focus-visible:outline"
                                               href="{{ route('workspace.drive.index', ['folder' => $file->folder->public_id]) }}">{{ $file->folder->name }}</a>
                                        </span>
                                    @endif
                                    <details class="mt-2 text-xs font-normal">
                                        <summary class="cursor-pointer text-[var(--app-text-muted)] underline-offset-4 hover:underline">Riwayat versi ({{ $file->versions->count() }})</summary>
                                        <ol class="mt-2 space-y-1 pl-5 text-[var(--app-text-muted)]">
                                            @foreach ($file->versions as $version)
                                                <li>
                                                    Versi {{ $version->version_number }} · {{ number_format($version->size_bytes) }} byte
                                                    <x-workspace.scan-status :file="$file" :version="$version" />
                                                    · <a class="underline-offset-4 hover:underline focus-visible:outline" href="{{ route('workspace.drive.files.versions.download', [$file, $version->id]) }}">Unduh versi ini</a>
                                                    @if ($version->version_number === $file->current_version)
                                                        <span class="font-medium text-[var(--app-text)]">· Aktif</span>
                                                    @else
                                                        <form method="POST" action="{{ route('workspace.drive.files.versions.restore', [$file, $version->id]) }}" class="inline">
                                                            @csrf
                                                            <button type="submit" class="underline-offset-4 hover:underline focus-visible:outline">· Pulihkan</button>
                                                        </form>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                        <form method="POST" action="{{ route('workspace.drive.files.versions.store', $file) }}" enctype="multipart/form-data" class="mt-3 space-y-2">
                                            @csrf
                                            <label for="drive-version-{{ $file->public_id }}" class="label">Unggah versi baru</label>
                                            <input id="drive-version-{{ $file->public_id }}" name="file" type="file" required
                                                   aria-label="Unggah versi baru untuk {{ $file->name }}" class="field w-full">
                                            <button type="submit" class="btn btn-secondary min-h-10">Simpan versi baru</button>
                                        </form>
                                    </details>
                                </th>
                                <td class="whitespace-nowrap px-5 py-4 text-[var(--app-text-muted)]">{{ $file->mime_type }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-[var(--app-text-muted)]">{{ $file->humanSize() }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-right sm:px-6">
                                    <form method="POST" action="{{ route('workspace.drive.files.rename', $file) }}" class="mb-2 flex gap-2">
                                        @csrf
                                        <label class="sr-only" for="rename-file-{{ $file->public_id }}">Nama baru untuk {{ $file->name }}</label>
                                        <input id="rename-file-{{ $file->public_id }}" name="name" value="{{ $file->name }}" maxlength="180" required class="field min-h-10 w-44">
                                        <input type="hidden" name="q" value="{{ $search }}">
                                        <input type="hidden" name="favorites" value="{{ $favoritesOnly ? '1' : '0' }}">
                                        @if ($currentFolder)<input type="hidden" name="folder" value="{{ $currentFolder->public_id }}">@endif
                                        <button type="submit" class="btn btn-secondary min-h-10">Ganti nama</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.drive.files.move', $file) }}" class="mb-2 flex gap-2">
                                        @csrf
                                        <label class="sr-only" for="move-file-{{ $file->public_id }}">Lokasi baru untuk {{ $file->name }}</label>
                                        <select id="move-file-{{ $file->public_id }}" name="destination" class="field min-h-10 w-40">
                                            <option value="root">Drive Saya (root)</option>
                                            @foreach ($moveDestinations as $destination)
                                                <option value="{{ $destination['id'] }}">{{ $destination['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-secondary min-h-10" type="submit">Pindahkan</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.drive.files.trash', $file) }}" class="mb-2">
                                        @csrf
                                        <button class="btn btn-ghost min-h-10" type="submit" aria-label="Pindahkan berkas {{ $file->name }} ke Trash">Pindahkan ke Trash</button>
                                    </form>
                                    <form method="POST" action="{{ route('workspace.drive.files.favorite', $file) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="favorite" value="{{ $file->is_favorite ? '0' : '1' }}">
                                        <input type="hidden" name="q" value="{{ $search }}">
                                        <input type="hidden" name="favorites" value="{{ $favoritesOnly ? '1' : '0' }}">
                                        @if ($currentFolder)<input type="hidden" name="folder" value="{{ $currentFolder->public_id }}">@endif
                                        <button type="submit" class="btn btn-secondary min-h-10 mr-1" aria-label="{{ $file->is_favorite ? 'Hapus dari favorit' : 'Tambahkan ke favorit' }} {{ $file->name }}">
                                            {{ $file->is_favorite ? '★ Favorit' : '☆ Favoritkan' }}
                                        </button>
                                    </form>
                                    @if (in_array(strtolower(pathinfo($file->name, PATHINFO_EXTENSION)), $wopiExtensions, true))
                                        <a href="{{ route('workspace.drive.files.collabora', $file) }}"
                                           class="btn btn-secondary min-h-10 mr-1"
                                           aria-label="Edit {{ $file->name }} di Collabora">Edit</a>
                                    @endif
                                    <a href="{{ route('workspace.drive.files.download', $file) }}"
                                       class="btn btn-secondary min-h-10"
                                       aria-label="Unduh {{ $file->name }}">
                                        <x-icon name="download" class="size-4" />
                                        Unduh
                                    </a>
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
