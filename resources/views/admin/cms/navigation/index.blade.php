@extends('components.app-shell')

@section('title', 'Navigasi Publik')
@section('page-title', 'Navigasi Publik')
@section('page-description', 'Atur tautan yang tampil di navigasi situs sekolah.')

@section('content')
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    @if ($errors->any())
        <x-alert variant="danger" class="mb-4" :message="$errors->first()" />
    @endif

    <section class="surface mb-6 p-5 sm:p-6" aria-labelledby="new-navigation-heading">
        <h2 id="new-navigation-heading" class="text-heading-3 font-semibold">Tambah tautan</h2>
        <form method="POST" action="{{ route('admin.cms.navigation.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            <x-form-field name="label" label="Label menu" required maxlength="80" :value="old('label')" />
            <x-form-field name="url" label="Alamat" hint="Path internal seperti /profil atau URL HTTPS." required :value="old('url')" />
            <x-form-field name="position" type="number" label="Urutan" min="0" max="9999" required :value="old('position', 10)" />
            <div class="flex flex-wrap items-end gap-5 pb-2 text-small">
                <label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="is_enabled" value="1" checked class="size-4"> Aktif</label>
                <label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="open_in_new_tab" value="1" class="size-4"> Buka tab baru</label>
            </div>
            <div class="sm:col-span-2 flex justify-end">
                <button type="submit" class="btn btn-primary">Tambah menu</button>
            </div>
        </form>
    </section>

    <section class="surface overflow-hidden" aria-labelledby="navigation-list-heading">
        <div class="border-b border-[var(--app-border)] p-5"><h2 id="navigation-list-heading" class="text-heading-3 font-semibold">Daftar tautan</h2></div>
        @if ($items->isEmpty())
            <p class="p-8 text-center text-small text-[var(--app-text-muted)]">Belum ada tautan kustom. Pengunjung masih memakai menu bawaan. Setelah menambah tautan, hanya tautan aktif di daftar ini yang tampil.</p>
        @else
            <div class="divide-y divide-[var(--app-border)]">
                @foreach ($items as $item)
                    <div class="p-5">
                        <form method="POST" action="{{ route('admin.cms.navigation.update', $item) }}" class="grid gap-4 sm:grid-cols-2">
                            @csrf
                            @method('PUT')
                            <x-form-field name="label" label="Label menu" required maxlength="80" :value="$item->label" />
                            <x-form-field name="url" label="Alamat" required :value="$item->url" />
                            <x-form-field name="position" type="number" label="Urutan" min="0" max="9999" required :value="$item->position" />
                            <div class="flex flex-wrap items-end gap-5 pb-2 text-small">
                                <label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="is_enabled" value="1" @checked($item->is_enabled) class="size-4"> Aktif</label>
                                <label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" name="open_in_new_tab" value="1" @checked($item->open_in_new_tab) class="size-4"> Buka tab baru</label>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="submit" class="btn btn-secondary">Simpan</button>
                            </div>
                        </form>
                        <div class="mt-2 flex justify-end">
                            <form method="POST" action="{{ route('admin.cms.navigation.destroy', $item) }}" onsubmit="return confirm('Hapus tautan ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary text-red-700">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
