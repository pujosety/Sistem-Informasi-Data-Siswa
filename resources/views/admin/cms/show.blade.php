@extends('components.app-shell')

@section('title', $post->title)
@section('page-title', $post->title)
@section('page-description', ($post->isPage() ? 'Halaman' : 'Artikel').' · '.$post->statusLabel())

@section('page-actions')
    @can('cms.posts.edit')
        <a href="{{ route('admin.cms.edit', $post) }}" class="btn btn-primary">
            <x-icon name="settings" class="w-4 h-4" />
            Ubah
        </a>
    @endcan
    <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
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
    Publishing lives here, not in the editor. The permission that gates it is
    `cms.posts.publish`, which kesiswaan deliberately does NOT hold — so a
    teacher can draft the school news and a head publishes it, which is the
    split §14 asks for.
--}}
<div class="max-w-3xl space-y-4">
    <x-card>
        <div class="flex flex-wrap items-center gap-3">
            <x-status-badge :status="$post->status" />
            @if ($post->is_public)
                <a href="{{ $post->url() }}" target="_blank" rel="noopener"
                   class="btn btn-secondary">
                    <x-icon name="link" class="w-4 h-4" />
                    Lihat di Situs Publik
                </a>
            @endif

            @can('cms.posts.publish')
                @if ($post->is_public)
                    <form method="POST" action="{{ route('admin.cms.unpublish', $post) }}">
                        @csrf
                        <button class="btn btn-secondary">
                            <x-icon name="eye-off" class="w-4 h-4" />
                            Tarik dari Situs
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.cms.publish', $post) }}"
                          x-data>
                        @csrf
                        <button class="btn btn-primary"
                                @click="if (! confirm('Terbitkan konten ini ke situs publik?')) $event.preventDefault()">
                            <x-icon name="check-circle" class="w-4 h-4" />
                            Terbitkan
                        </button>
                    </form>
                @endif
            @endcan

            @can('cms.posts.edit')
                @if ($post->status === \App\Models\Post::DRAFT)
                    <form method="POST" action="{{ route('admin.cms.submit', $post) }}">
                        @csrf
                        <button class="btn btn-secondary">
                            <x-icon name="send" class="w-4 h-4" />
                            Kirim untuk Ditinjau
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.cms.revisions', $post) }}" class="btn btn-secondary">
                    <x-icon name="history" class="w-4 h-4" />
                    Revisi ({{ $post->revisions->count() }})
                </a>
            @endcan
        </div>

        @unless (auth()->user()->can('cms.posts.publish'))
            <p class="help-text mt-3">
                Anda dapat menulis dan mengubah, tetapi penerbitan diputuskan oleh
                pemangku izin terbitkan.
            </p>
        @endunless
    </x-card>

    <x-card title="Ringkasan" icon="file-text">
        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Penulis</dt>
                <dd class="text-[var(--app-text)]">{{ $post->author?->name ?? 'Tanpa penulis' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Kategori</dt>
                <dd class="text-[var(--app-text)]">{{ $post->category?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Slug</dt>
                <dd class="font-mono text-[var(--app-text)]">{{ $post->slug }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Dibuat</dt>
                <dd class="text-[var(--app-text)]">{{ $post->created_at?->format('d F Y H:i') }}</dd>
            </div>
        </dl>

        @if ($post->tags->isNotEmpty())
            <div class="mt-4 flex flex-wrap gap-1">
                @foreach ($post->tags as $tag)
                    <span class="badge badge-neutral">{{ $tag->name }}</span>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card title="Isi" icon="file-text">
        @if (filled($post->body))
            <div class="prose prose-sm max-w-none">{!! $post->body !!}</div>
        @else
            <p class="text-[var(--app-text-subtle)]">Belum ada isi.</p>
        @endif
    </x-card>
</div>
@endsection
