@extends('components.app-shell')

@section('title', 'LYFLA Studio')
@section('page-title', 'LYFLA Studio')
@section('page-description', 'Kelola konten dan situs publik sekolah.')

@section('page-actions')
    @can('cms.posts.create')
        <a href="{{ route('admin.cms.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="size-4" />
            Tulis konten
        </a>
    @endcan
@endsection

@section('content')
    <section aria-labelledby="cms-overview-heading">
        <h2 id="cms-overview-heading" class="sr-only">Ringkasan CMS</h2>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['posts', 'Artikel', 'file-text'],
                ['pages', 'Halaman', 'files'],
                ['drafts', 'Draft', 'clock'],
                ['review', 'Menunggu tinjauan', 'inbox'],
            ] as [$key, $label, $icon])
                <x-card :title="$label" :icon="$icon">
                    <p class="text-2xl font-bold tabular-nums" data-cms-metric="{{ $key }}" data-cms-value="{{ $metrics[$key] }}">{{ $metrics[$key] }}</p>
                </x-card>
            @endforeach
        </div>
    </section>

    <section class="mt-6" aria-labelledby="cms-tools-heading">
        <div class="mb-3">
            <h2 id="cms-tools-heading" class="text-heading-3 font-semibold">Kelola situs</h2>
            <p class="mt-1 text-small text-[var(--app-text-muted)]">Pilih area yang ingin Anda kerjakan.</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @can('cms.view')
                <a href="{{ route('admin.cms.index') }}" class="surface group p-4 transition hover:border-[var(--app-primary)] focus-visible:outline-2 focus-visible:outline-offset-2">
                    <x-icon name="file-text" class="size-5 text-[var(--app-primary)]" />
                    <h3 class="mt-3 font-semibold group-hover:underline">Kelola Artikel & Halaman</h3>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">Tulis, tinjau, terbitkan, dan pulihkan revisi.</p>
                </a>
            @endcan
            @can('cms.media.manage')
                <a href="{{ route('admin.media.index') }}" class="surface group p-4 transition hover:border-[var(--app-primary)] focus-visible:outline-2 focus-visible:outline-offset-2">
                    <x-icon name="image" class="size-5 text-[var(--app-primary)]" />
                    <h3 class="mt-3 font-semibold group-hover:underline">Pustaka Media</h3>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">{{ $metrics['media'] }} aset dengan metadata dan teks alternatif.</p>
                </a>
            @endcan
            @can('cms.navigation.manage')
                <a href="{{ route('admin.cms.navigation.index') }}" class="surface group p-4 transition hover:border-[var(--app-primary)] focus-visible:outline-2 focus-visible:outline-offset-2">
                    <x-icon name="menu" class="size-5 text-[var(--app-primary)]" />
                    <h3 class="mt-3 font-semibold group-hover:underline">Navigasi Publik</h3>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">Atur tautan header di situs sekolah.</p>
                </a>
            @endcan
            @can('cms.view')
                <a href="{{ route('admin.landing.index') }}" class="surface group p-4 transition hover:border-[var(--app-primary)] focus-visible:outline-2 focus-visible:outline-offset-2">
                    <x-icon name="layout-dashboard" class="size-5 text-[var(--app-primary)]" />
                    <h3 class="mt-3 font-semibold group-hover:underline">Landing Page</h3>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">Atur section yang tersedia pada halaman utama.</p>
                </a>
            @endcan
        </div>
    </section>

    <section class="surface mt-6 overflow-hidden" aria-labelledby="cms-recent-heading">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-[var(--app-border)] p-4 sm:p-5">
            <div>
                <h2 id="cms-recent-heading" class="font-semibold">Konten terbaru</h2>
                <p class="mt-1 text-caption text-[var(--app-text-muted)]">Diurutkan dari perubahan terakhir.</p>
            </div>
            @can('cms.view')
                <a href="{{ route('admin.cms.index') }}" class="btn btn-secondary">Lihat semua</a>
            @endcan
        </div>

        @if ($recentPosts->isEmpty())
            <div class="p-6 text-center">
                <p class="font-semibold">Belum ada konten</p>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">Simpan sebagai draft dulu; publikasi selalu menjadi tindakan terpisah.</p>
            </div>
        @else
            <ul class="divide-y divide-[var(--app-border)]">
                @foreach ($recentPosts as $post)
                    <li class="flex flex-wrap items-center justify-between gap-3 p-4 sm:px-5">
                        <div class="min-w-0">
                            <a href="{{ route('admin.cms.show', $post) }}" class="font-medium hover:underline">{{ $post->title }}</a>
                            <p class="mt-1 text-caption text-[var(--app-text-muted)]">
                                {{ $post->isPage() ? 'Halaman' : 'Artikel' }} · {{ $post->statusLabel() }}
                            </p>
                        </div>
                        @can('cms.posts.edit')
                            <a href="{{ route('admin.cms.edit', $post) }}" class="btn btn-secondary">Ubah</a>
                        @endcan
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
