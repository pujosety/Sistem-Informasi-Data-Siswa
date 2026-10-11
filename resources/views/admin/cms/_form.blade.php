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
    $initialBlocks = old('blocks', $post->blocks ?? []);
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

        <x-card title="Blok konten" icon="layout-dashboard"
                description="Blok terstruktur tampil saat halaman dibaca publik. Jika tidak ada blok, isi klasik di atas tetap digunakan.">
            <div x-data="{
                blocks: @js(is_array($initialBlocks) ? array_values($initialBlocks) : []),
                add(type) {
                    const block = { type, text: '' };
                    if (type === 'heading') block.level = 2;
                    if (type === 'button') block.url = '';
                    this.blocks.push(block);
                },
                move(index, direction) {
                    const destination = index + direction;
                    if (destination < 0 || destination >= this.blocks.length) return;
                    const [block] = this.blocks.splice(index, 1);
                    this.blocks.splice(destination, 0, block);
                },
                remove(index) {
                    this.blocks.splice(index, 1);
                }
            }" class="space-y-4">
                <input type="hidden" name="blocks_present" value="1">

                <p x-show="blocks.length === 0" role="status" aria-live="polite"
                   class="rounded-[var(--radius-md)] border border-dashed border-[var(--app-border)] p-4 text-sm text-[var(--app-text-muted)]">
                    Belum ada blok. Tambahkan judul, paragraf, atau tombol.
                </p>

                <template x-for="(block, index) in blocks" :key="index">
                    <fieldset class="space-y-4 rounded-[var(--radius-md)] border border-[var(--app-border)] p-4">
                        <legend class="px-1 text-sm font-semibold text-[var(--app-text)]">
                            Blok <span x-text="index + 1"></span>
                        </legend>

                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" class="btn btn-secondary"
                                    x-on:click="move(index, -1)" x-bind:disabled="index === 0"
                                    x-bind:aria-label="'Pindahkan blok ke atas, posisi ' + (index + 1)">
                                Pindahkan ke atas
                            </button>
                            <button type="button" class="btn btn-secondary"
                                    x-on:click="move(index, 1)" x-bind:disabled="index === blocks.length - 1"
                                    x-bind:aria-label="'Pindahkan blok ke bawah, posisi ' + (index + 1)">
                                Pindahkan ke bawah
                            </button>
                            <button type="button" class="btn btn-secondary text-[var(--app-danger)]"
                                    x-on:click="remove(index)" x-bind:aria-label="'Hapus blok ' + (index + 1)">
                                Hapus blok
                            </button>
                        </div>

                        <div>
                            <label class="label" x-bind:for="'cms-block-type-' + index">Jenis blok</label>
                            <select class="field" x-bind:id="'cms-block-type-' + index"
                                    x-bind:name="'blocks[' + index + '][type]'" x-model="block.type">
                                <option value="heading">Judul</option>
                                <option value="paragraph">Paragraf</option>
                                <option value="button">Tombol tautan</option>
                            </select>
                        </div>

                        <div>
                            <label class="label" x-bind:for="'cms-block-text-' + index">Teks</label>
                            <textarea class="field" rows="3" maxlength="5000"
                                      x-bind:id="'cms-block-text-' + index"
                                      x-bind:name="'blocks[' + index + '][text]'" x-model="block.text"></textarea>
                        </div>

                        <template x-if="block.type === 'heading'">
                            <div>
                                <label class="label" x-bind:for="'cms-block-level-' + index">Tingkat judul</label>
                                <select class="field" x-bind:id="'cms-block-level-' + index"
                                        x-bind:name="'blocks[' + index + '][level]'" x-model="block.level">
                                    <option value="2">H2</option>
                                    <option value="3">H3</option>
                                    <option value="4">H4</option>
                                </select>
                            </div>
                        </template>

                        <template x-if="block.type === 'button'">
                            <div>
                                <label class="label" x-bind:for="'cms-block-url-' + index">Tautan tombol</label>
                                <input class="field" type="text" maxlength="2048" autocomplete="url"
                                       placeholder="/ppdb atau https://contoh.id"
                                       x-bind:id="'cms-block-url-' + index"
                                       x-bind:name="'blocks[' + index + '][url]'" x-model="block.url">
                                <p class="mt-1 text-caption text-[var(--app-text-subtle)]">Gunakan path internal atau alamat HTTPS.</p>
                            </div>
                        </template>
                    </fieldset>
                </template>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary" x-on:click="add('heading')">Tambah judul</button>
                    <button type="button" class="btn btn-secondary" x-on:click="add('paragraph')">Tambah paragraf</button>
                    <button type="button" class="btn btn-secondary" x-on:click="add('button')">Tambah tombol</button>
                </div>

                @foreach ($errors->getMessages() as $field => $messages)
                    @if (str_starts_with($field, 'blocks'))
                        <p role="alert" class="text-sm text-[var(--app-danger)]">{{ $messages[0] }}</p>
                    @endif
                @endforeach
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

    {{--
            GAMBAR ARTIKEL

            A SEPARATE FORM, deliberately, and not a field inside the editor
            above.

            The editor's form posts to CmsPostService and every field on it is
            copied onto the post. An `<input type="file">` inside it would mean
            the content save and the image save share one request, one failure
            mode, and one transaction boundary — a rejected image would then
            block the article, and an article that cannot be saved because of a
            photo is the wrong trade in either direction.

            So the images are attached by their own request to
            admin.cms.media.attach, gated on cms.posts.edit, and a failure there
            leaves the article exactly as it was.
        --}}
        @if ($post->exists && auth()->user()?->can('cms.posts.edit'))
            @php
                $attached = $post->relationLoaded('media')
                    ? $post->getRelation('media')
                    : $post->media()->get();
                $library = \App\Models\Media::query()
                    ->orderByDesc('id')
                    ->limit(60)
                    ->get();
                $attachedIds = $attached->pluck('id')->all();
            @endphp

            <x-card title="Gambar Artikel" icon="image"
                    description="Pilih gambar yang sudah ada di pustaka media.">
                @if ($library->isEmpty())
                    <p class="text-sm text-[var(--app-text-subtle)]">
                        Pustaka media masih kosong.
                        @can('cms.media.manage')
                            <a href="{{ route('admin.media.index') }}" class="text-brand-700 underline">Unggah gambar dulu.</a>
                        @else
                            Hubungi pengelola konten yang memiliki izin <code>cms.media.manage</code> untuk mengunggah gambar.
                        @endcan
                    </p>
                @else
                    <form method="POST" action="{{ route('admin.cms.media.attach', $post) }}">
                        @csrf
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                            @foreach ($library as $item)
                                <label class="block border rounded-lg p-1.5 cursor-pointer
                                              {{ in_array($item->id, $attachedIds, true) ? 'border-brand-500 bg-brand-50' : 'border-[var(--app-border)]' }}">
                                    <input type="checkbox" name="media_ids[]" value="{{ $item->id }}"
                                           class="mr-1"
                                           @checked(in_array($item->id, $attachedIds, true)) />
                                    <img src="{{ $item->url() }}"
                                         alt="{{ $item->alt_text ?? $item->displayName() }}"
                                         class="w-full h-16 object-cover rounded mt-1" loading="lazy" />
                                    <span class="block text-caption truncate mt-1" title="{{ $item->displayName() }}">
                                        {{ $item->displayName() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <p class="text-caption text-[var(--app-text-subtle)] mt-3">
                            Kosongkan semua centang untuk melepas semua gambar dari artikel ini.
                        </p>

                        <button type="submit" class="btn btn-secondary mt-3">Simpan Gambar Artikel</button>
                    </form>
                @endif
            </x-card>
        @endif
</div>
