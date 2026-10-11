@extends('components.app-shell')
@section('title', 'Workspace · Dibagikan kepada saya')
@section('page-title', 'Dibagikan kepada saya')
@section('page-description', 'Berkas privat sekolah aktif dengan izin baca saja.')
@section('content')
<div class="mx-auto max-w-4xl space-y-4">
    <a class="btn btn-secondary" href="{{ route('workspace.drive.index') }}">Drive Saya</a>
    <p>Izin diperiksa lagi setiap kali mengunduh. Berkas di Trash, izin dicabut/kedaluwarsa, dan versi tanpa hasil pemeriksaan bersih tidak dapat diunduh.</p>
    @forelse ($shares as $share)
        <article class="rounded-lg border border-[var(--app-border)] p-5">
            <h2 class="text-h3">{{ $share->file->name }}</h2>
            <p>Pemilik: {{ $share->owner?->name }} · {{ $share->file->humanSize() }} · Baca saja</p>
            <p>Batas: {{ $share->expires_at?->format('d M Y H:i') ?? 'Sampai dicabut' }}</p>
            <a class="btn btn-secondary mt-3" href="{{ route('workspace.shares.download', $share->public_id) }}" aria-label="Unduh {{ $share->file->name }}">Unduh versi aktif</a>
        </article>
    @empty
        <p>Belum ada berkas yang dibagikan kepada Anda.</p>
    @endforelse
    {{ $shares->links() }}
</div>
@endsection
