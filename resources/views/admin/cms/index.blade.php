@extends('components.app-shell')

@section('title', 'Konten')
@section('page-title', 'Konten')
@section('page-description', $posts->total().' artikel dan halaman')

@section('page-actions')
    @can('cms.posts.create')
        <a href="{{ route('admin.cms.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" />
            Tulis Artikel
        </a>
    @endcan
@endsection

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif
@if (session('error'))
    <x-alert variant="danger" class="mb-4" :message="session('error')" />
@endif

{{--
    A scheduled post is only published by publishDue(), which the scheduler
    calls. On a single-container host no worker runs, so anything due stays
    "scheduled" forever. Saying so is better than a status list that quietly
    never changes.
--}}
@if ($dueCount > 0)
    <x-alert variant="warning" class="mb-4"
             title="{{ $dueCount }} konten terjadwal sudah jatuh tempo"
             message="Penjadwalan otomatis relies pada scheduler yang tidak berjalan di host ini. Terbitkan sekarang dengan tombol di bawah.">
        @can('cms.posts.publish')
            <form method="POST" action="{{ route('admin.cms.publish-due') }}" class="mt-3">
                @csrf
                <button class="btn btn-primary">
                    <x-icon name="check-circle" class="w-4 h-4" />
                    Terbitkan yang Jatuh Tempo
                </button>
            </form>
        @endcan
    </x-alert>
@endif

<form method="GET" class="surface p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
    <div class="lg:col-span-2">
        <label for="q" class="sr-only">Cari konten</label>
        <div class="relative">
            <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
            <input id="q" name="q" value="{{ $q }}" class="field pl-9" placeholder="Cari judul atau slug">
        </div>
    </div>
    <div>
        <label for="kind" class="sr-only">Jenis</label>
        <select id="kind" name="kind" class="field">
            <option value="">Artikel & halaman</option>
            @foreach ($kinds as $value => $label)
                <option value="{{ $value }}" @selected(($filters['kind'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="status" class="sr-only">Status</label>
        <select id="status" name="status" class="field">
            <option value="">Semua status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                    {{ ucfirst($status) }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary flex-1 justify-center" type="submit">Filter</button>
        <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary shrink-0" aria-label="Reset">
            <x-icon name="refresh-cw" class="w-4 h-4" />
        </a>
    </div>
</form>

@forelse ($posts as $post)
    <div class="surface p-4 mb-3">
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.cms.show', $post) }}" class="font-semibold text-[var(--app-text)] hover:underline">
                        {{ $post->title }}
                    </a>
                    <x-status-badge :status="$post->status" />
                    <span class="badge badge-neutral">{{ $post->isPage() ? 'Halaman' : 'Artikel' }}</span>
                    @if ($post->is_public)
                        <span class="badge badge-success">
                            <x-icon name="link" class="w-3 h-3" />
                            Publik
                        </span>
                    @endif
                </div>

                <p class="text-small text-[var(--app-text-muted)] mt-1 line-clamp-2">
                    {{ $post->excerpt ?: ' Belum ada ringkasan.' }}
                </p>

                <p class="text-caption text-[var(--app-text-subtle)] mt-1.5">
                    {{ $post->author?->name ?? 'Tanpa penulis' }}
                    @if ($post->category) · {{ $post->category->name }} @endif
                    · diubah {{ $post->updated_at?->diffForHumans() }}
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @can('cms.posts.edit')
                    <a href="{{ route('admin.cms.edit', $post) }}" class="btn btn-secondary">
                        <x-icon name="settings" class="w-4 h-4" />
                        Ubah
                    </a>
                @endcan
                <a href="{{ route('admin.cms.show', $post) }}" class="btn btn-secondary" aria-label="Detail">
                    <x-icon name="eye" class="w-4 h-4" />
                </a>
            </div>
        </div>
    </div>
@empty
    <x-card>
        <x-empty-state icon="file-text"
                       title="Belum ada konten"
                       description="Tulis artikel berita sekolah atau halaman profil. Semuanya disimpan sebagai draft sampai Anda menerbitkannya." />
        @can('cms.posts.create')
            <div class="flex justify-center mt-4">
                <a href="{{ route('admin.cms.create') }}" class="btn btn-primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    Tulis Artikel
                </a>
            </div>
        @endcan
    </x-card>
@endforelse

@if ($posts->hasPages())
    <div class="mt-4">{{ $posts->links() }}</div>
@endif
@endsection
