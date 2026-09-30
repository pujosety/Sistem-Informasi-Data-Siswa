@php
    /*
     * The editor, shared by create and edit.
     *
     * THERE IS NO PUBLISH BUTTON IN HERE, AND THAT IS THE POINT.
     *
     * The form has a save action and nothing else. Publishing is a separate
     * route with a separate permission, on the detail screen, so a draft cannot
     * reach the public site through a form that happens to submit a status
     * field. The two-stage workflow starts at draft, and a writer cannot skip
     * the review by adding one hidden input.
     */
    $isEdit = $post->exists;
    $tagValue = old('tags', $isEdit ? $post->tags->pluck('name')->implode(', ') : '');
@endphp

<div class="max-w-3xl space-y-4 sm:space-y-5">
    <form method="POST"
          action="{{ $isEdit ? route('admin.cms.update', $post) : route('admin.cms.store') }}"
          class="space-y-4 sm:space-y-5" novalidate>
        @csrf
        @if ($isEdit)
            @method('PUT')
        @endif

        <x-card title="Isi" icon="file-text">
            <div class="space-y-4">
                <div class="grid sm:grid-cols-3 gap-4">
                    <x-form-field name="kind" type="select" label="Jenis" required class="sm:col-span-1"
                                  :value="old('kind', $post->kind)" placeholder="false">
                        @foreach ($kinds as $value => $label)
                            <option value="{{ $value }}" @selected(old('kind', $post->kind) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-form-field>

                    <x-form-field name="category_id" type="select" label="Kategori" class="sm:col-span-2"
                                  :value="old('category_id', $post->category_id)" placeholder="— Tanpa kategori —">
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((int) old('category_id', $post->category_id) === $id)>
                                {{ $name }}
                            </option>
                        @endforeach
                    </x-form-field>
                </div>

                <x-form-field name="title" label="Judul" required :value="$post->title"
                              placeholder="Contoh: Penerimaan Peserta Didik Baru" />

                <x-form-field name="slug" label="Slug URL" :value="$post->slug"
                              placeholder="Dibuat otomatis dari judul"
                              hint="{{ $post->is_public ? 'Konten publik tidak bisa mengganti slug — tautan lama harus tetap hidup.' : 'Kosongkan untuk dibuat otomatis dari judul.' }}" />

                <x-form-field name="excerpt" type="textarea" label="Ringkasan" :rows="2" :value="$post->excerpt"
                              hint="Tampil di daftar berita. Kosongkan untuk diambil dari paragraf pertama." />

                <x-form-field name="body" type="textarea" label="Isi" :rows="14" :value="$post->body"
                              placeholder="Tulis isi artikel di sini. Formatting dasar didukung; skrip dan tag berbahaya dibuang saat disimpan." />
            </div>
        </x-card>

        <x-card title="Label & SEO" icon="search"
                description="Ditampilkan di halaman publik, tidak di daftar admin.">
            <div class="space-y-4">
                <x-form-field name="tags" label="Label" :value="$tagValue"
                              hint="Pisahkan dengan koma." />

                <x-form-field name="meta_title" label="Meta title" :value="$post->meta_title"
                              hint="Kosongkan untuk memakai judul." />
                <x-form-field name="meta_description" type="textarea" :rows="2"
                              label="Meta description" :value="$post->meta_description" />
            </div>
        </x-card>

        <div class="flex flex-col sm:flex-row sm:justify-end gap-2">
            <a href="{{ $isEdit ? route('admin.cms.show', $post) : route('admin.cms.index') }}"
               class="btn btn-secondary justify-center">Batal</a>
            <button type="submit" class="btn btn-primary justify-center">
                <x-icon name="save" class="w-4 h-4" />
                {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Draft' }}
            </button>
        </div>

        <p class="text-caption text-[var(--app-text-subtle)] text-right">
            Menyimpan tidak menerbitkan. Terbitkan dari halaman detail.
        </p>
    </form>
</div>
