@extends('components.app-shell')

@section('title', 'Revisi')
@section('page-title', 'Revisi')
@section('page-description', $post->title)

@section('page-actions')
    <a href="{{ route('admin.cms.show', $post) }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Detail
    </a>
@endsection

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif

<div class="max-w-3xl space-y-4">
    <x-alert variant="info"
             title="Setiap penyimpanan menyimpan versi sebelumnya"
             message="Mengembalikan revisi hanya mengubah isi. Status terbit tidak ikut berubah, jadi mengembalikan konten tidak pernah mengirimnya ke situs publik tanpa keputusan tersendiri." />

    @forelse ($revisions as $revision)
        <x-card>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="font-semibold text-[var(--app-text)]">
                        {{ $revision->created_at?->format('d F Y H:i') }}
                    </p>
                    <p class="text-caption text-[var(--app-text-muted)]">
                        oleh {{ $revision->author?->name ?? 'Tanpa nama' }}
                        · {{ $revision->created_at?->diffForHumans() }}
                    </p>
                </div>

                <form method="POST"
                      action="{{ route('admin.cms.revisions.restore', [$post, $revision]) }}"
                      x-data
                      @submit="if (! confirm('Kembalikan isi ke versi ini? Status terbit tidak berubah.')) $event.preventDefault()">
                    @csrf
                    <button class="btn btn-secondary">
                        <x-icon name="rotate-ccw" class="w-4 h-4" />
                        Kembalikan
                    </button>
                </form>
            </div>

            <p class="text-small text-[var(--app-text-muted)] mt-3 line-clamp-3">
                {{ $revision->payload['title'] ?? '—' }}
            </p>
        </x-card>
    @empty
        <x-card>
            <x-empty-state icon="history"
                           title="Belum ada revisi"
                           description="Revisi tersimpan otomatis setiap kali konten diubah, jadi ini terisi setelah penyimpanan kedua."
                           compact />
        </x-card>
    @endforelse
</div>
@endsection
