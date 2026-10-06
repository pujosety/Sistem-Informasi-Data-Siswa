@extends('components.app-shell')

@section('title', 'Landing page')
@section('page-title', 'Landing page')
@section('page-description', 'Atur urutan dan konten halaman utama LYFLA.')

@section('content')
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    <div class="surface overflow-hidden">
        @if ($sections->isEmpty())
            <div class="p-10 text-center">
                <x-icon name="layout-dashboard" class="mx-auto size-10 text-[var(--app-text-subtle)]" />
                <p class="mt-3 text-body font-semibold">Landing page belum diisi</p>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">Jalankan seeder atau tambahkan section dari database sebelum mengeditnya.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-small">
                    <thead class="border-b border-[var(--app-border)] bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Urutan</th>
                            <th class="px-5 py-3 font-semibold">Section</th>
                            <th class="px-5 py-3 font-semibold">Tipe</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--app-border)]">
                        @foreach ($sections as $section)
                            <tr>
                                <td class="px-5 py-4 tabular-nums">{{ $section->position }}</td>
                                <td class="px-5 py-4"><p class="font-semibold">{{ $section->title ?: 'Tanpa judul' }}</p><p class="text-caption text-[var(--app-text-muted)]">{{ $section->subtitle }}</p></td>
                                <td class="px-5 py-4"><code>{{ $section->type }}</code></td>
                                <td class="px-5 py-4"><span class="inline-flex rounded-full bg-[var(--app-surface-muted)] px-2.5 py-1 text-caption font-semibold">{{ $section->is_enabled ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('admin.landing.edit', $section) }}" class="btn btn-secondary py-1.5 text-caption">Ubah</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
