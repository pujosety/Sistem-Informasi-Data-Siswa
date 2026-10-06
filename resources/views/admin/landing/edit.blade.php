@extends('components.app-shell')

@section('title', 'Ubah landing section')
@section('page-title', 'Ubah landing section')
@section('page-description', $section->title ?: $section->type)

@section('content')
    <form method="POST" action="{{ route('admin.landing.update', $section) }}" class="surface max-w-4xl p-6 sm:p-8">
        @csrf
        @method('PUT')
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form-field name="title" label="Judul" :value="old('title', $section->title)" />
            <x-form-field name="subtitle" label="Subjudul" :value="old('subtitle', $section->subtitle)" />
            <x-form-field name="type" type="select" label="Tipe" required :value="old('type', $section->type)">
                @foreach (\App\Models\LandingSection::TYPES as $type)
                    <option value="{{ $type }}" @selected(old('type', $section->type) === $type)>{{ $type }}</option>
                @endforeach
            </x-form-field>
            <x-form-field name="position" type="number" label="Urutan" required :value="old('position', $section->position)" step="0.01" min="0" />
            <x-form-field name="media_id" type="number" label="ID media (opsional)" :value="old('media_id', $section->media_id)" min="1" />
            <div class="flex items-end pb-2">
                <label class="inline-flex min-h-11 items-center gap-3 text-small font-semibold">
                    <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $section->is_enabled)) class="size-4 rounded border-[var(--app-border-strong)] text-[var(--app-primary)]">
                    Section aktif di halaman utama
                </label>
            </div>
        </div>

        <x-form-field name="body" type="textarea" label="Isi" :rows="6" class="mt-5" :value="old('body', $section->body)" />
        <x-form-field name="content" type="textarea" label="Content JSON" hint="Gunakan JSON valid untuk CTA, items, stats, FAQ, atau konfigurasi block." :rows="14" class="mt-5 font-mono" :value="old('content', json_encode($section->content ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))" />

        <div class="mt-6 flex flex-wrap justify-end gap-3">
            <a href="{{ route('admin.landing.index') }}" class="btn btn-secondary">Batal</a>
            @can('cms.posts.edit')
                <button type="submit" class="btn btn-primary"><x-icon name="save" class="size-4" /> Simpan perubahan</button>
            @endcan
        </div>
    </form>
@endsection
