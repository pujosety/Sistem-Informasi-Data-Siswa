@extends('components.app-shell')

@section('title', 'Pustaka Media')
@section('page-title', 'Pustaka Media')
@section('page-description', $items->total().' gambar tersedia untuk artikel dan halaman')

@section('page-actions')
    <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">
        <x-icon name="file-text" class="w-4 h-4" />
        Kembali ke Artikel
    </a>
@endsection

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif
@if (session('error'))
    <x-alert variant="danger" class="mb-4" :message="session('error')" />
@endif

{{--
    WHY THIS FORM SAYS WHAT IT ACCEPTS

    "JPG, PNG, WEBP, GIF — maksimal {{ $maxKb }} KB" is not decoration. A user
    who uploads a 12 MB photo and is then told "invalid image" learns nothing
    and tries again the same way. The constraint is stated before the click.

    The form has `image` and `max` as a first filter, but the check that
    actually matters reads the bytes: a PHP file renamed to .jpg passes this
    form's client-side rules and is still rejected, because CmsMediaService
    sniffs the real MIME. `accept` is a hint for the file picker, not a control.
--}}
<x-card title="Unggah Gambar" icon="upload" class="mb-4">
    <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data"
          class="space-y-3">
        @csrf

        <div class="grid sm:grid-cols-3 gap-4">
            {{-- A raw <input type="file"> rather than <x-form-field>: the shared
                 field always renders a `value` attribute, and a file input must
                 not be pre-populated — a browser ignores it at best and treats
                 it as a scripting vector at worst. --}}
            <div class="sm:col-span-1">
                <label class="label" for="f-media-file">
                    Berkas gambar
                    <span class="text-[var(--app-danger)]" aria-hidden="true">*</span>
                    <span class="sr-only">(wajib diisi)</span>
                </label>
                <input id="f-media-file" type="file" name="file" required
                       accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                       @error('file') aria-invalid="true" aria-describedby="f-media-file-error" @enderror
                       class="field @error('file') field-error @enderror" />
                @error('file')
                    <p id="f-media-file-error" class="text-caption text-[var(--app-danger)]">{{ $message }}</p>
                @else
                    <p class="text-caption text-[var(--app-text-subtle)]">
                        JPG, PNG, WEBP, atau GIF. Maksimal {{ number_format($maxKb) }} KB.
                    </p>
                @enderror
            </div>

            <x-form-field name="alt_text" class="sm:col-span-2"
                          placeholder="Contoh: Guru dan siswa di halaman depan sekolah"
                          label="Teks alternatif (alt)"
                          hint="Dibaca screen reader. Jelaskan gambar, jangan tulis 'gambar'." />

            <x-form-field name="caption" type="textarea" :rows="2" class="sm:col-span-3"
                          label="Keterangan"
                          placeholder="Tampil di bawah gambar pada halaman publik." />
        </div>

        <p class="text-caption text-[var(--app-text-subtle)]">
            Berkas akan disimpan dengan nama acak di disk <code>public</code>. Format aslinya dicek
            dari isi berkas, bukan dari nama yang dikirim browser.
        </p>

        <button type="submit" class="btn btn-primary">
            <x-icon name="upload" class="w-4 h-4" />
            Unggah
        </button>
    </form>
</x-card>

<x-card title="Semua Gambar" icon="image">
    <form method="GET" action="{{ route('admin.media.index') }}" class="mb-4 flex gap-2">
        <input type="search" name="q" value="{{ $q }}" placeholder="Cari nama berkas atau teks alternatif"
               class="input flex-1" />
        <button type="submit" class="btn btn-secondary">Cari</button>
        @if ($q)
            <a href="{{ route('admin.media.index') }}" class="btn btn-secondary">Reset</a>
        @endif
    </form>

    @if ($items->isEmpty())
        <x-empty-state icon="image" title="Belum ada gambar"
                      description="Unggah gambar pertama untuk dipakai di artikel." />
    @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            @foreach ($items as $item)
                <div class="border border-[var(--app-border)] rounded-lg overflow-hidden bg-white">
                    <div class="aspect-video bg-ink-50 flex items-center justify-center overflow-hidden">
                        @if ($item->isMissingOnDisk())
                            <span class="text-caption text-[var(--app-danger)] text-center px-2">
                                Berkas hilang dari disk
                            </span>
                        @else
                            {{-- The bytes are served by the brand-asset style route pattern; until
                                 the media file route exists, fall back to the disk URL. --}}
                            <img src="{{ $item->url() }}"
                                 alt="{{ $item->alt_text ?? $item->displayName() }}"
                                 class="w-full h-full object-cover" loading="lazy" />
                        @endif
                    </div>

                    <div class="p-3 space-y-2">
                        <p class="text-sm font-medium text-[var(--app-text)] truncate" title="{{ $item->displayName() }}">
                            {{ $item->displayName() }}
                        </p>
                        <p class="text-caption text-[var(--app-text-subtle)]">
                            {{ $item->humanSize() }}
                            @if ($item->width)
                                &middot; {{ $item->width }}&times;{{ $item->height }}
                            @endif
                            &middot; {{ $item->posts()->count() }} artikel
                        </p>

                        <details>
                            <summary class="text-caption cursor-pointer text-brand-700">Keterangan</summary>
                            <form method="POST" action="{{ route('admin.media.update', $item) }}"
                                  enctype="multipart/form-data"
                                  class="mt-2 space-y-2">
                                @csrf
                                @method('PUT')
                                <label class="label" for="replace-{{ $item->id }}">Ganti file gambar</label>
                                <input id="replace-{{ $item->id }}" type="file" name="file"
                                       accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                                       class="field" />
                                <p class="text-caption text-[var(--app-text-subtle)]">Kosongkan jika hanya mengubah keterangan.</p>
                                <x-form-field name="alt_text" :value="old('alt_text', $item->alt_text)" />
                                <x-form-field name="caption" type="textarea" :rows="2"
                                              :value="old('caption', $item->caption)" />
                                <button type="submit" class="btn btn-secondary btn-sm">Simpan</button>
                            </form>
                        </details>

                        <form method="POST" action="{{ route('admin.media.destroy', $item) }}"
                              onsubmit="return confirm('Hapus gambar ini dari pustaka? Artikel yang memakainya tetap tampil.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-secondary btn-sm text-[var(--app-danger)]">
                                <x-icon name="trash" class="w-4 h-4" />
                                Hapus
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $items->links() }}
        </div>
    @endif
</x-card>

@endsection
