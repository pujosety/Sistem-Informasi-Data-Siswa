@extends('components.app-shell')
@section('title', 'Workspace · Izin baca')
@section('page-title', 'Kelola izin baca')
@section('page-description', 'Berbagi privat kepada akun aktif di sekolah yang sama.')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a class="btn btn-secondary" href="{{ route('workspace.drive.index') }}">Kembali ke Drive Saya</a>
    <a class="btn btn-secondary" href="{{ route('workspace.shares.received') }}">Dibagikan kepada saya</a>
    @if (session('success'))<x-alert variant="success" :message="session('success')" />@endif
    <h2 class="text-h3">{{ $file->name }}</h2>
    <p>Penerima hanya dapat mengunduh versi aktif yang lolos pemeriksaan keamanan. Tidak dapat mengedit, mengunggah versi, atau membuka Collabora. Tidak ada tautan anonim.</p>
    <form method="POST" action="{{ route('workspace.shares.grant', $file->public_id) }}" class="space-y-4 rounded-lg border border-[var(--app-border)] p-5">
        @csrf
        <div>
            <label for="share-email" class="label">Email akun penerima (persis seperti terdaftar)</label>
            <input id="share-email" name="recipient_email" type="email" required maxlength="255" value="{{ old('recipient_email') }}" class="field w-full" aria-describedby="share-email-help">
            <p id="share-email-help" class="help-text">Akun aktif harus mempunyai penugasan pengajar atau keanggotaan LMS aktif di sekolah ini. Akun tanpa bukti keanggotaan sekolah tidak dapat menerima berkas.</p>
            @error('recipient_email')<p role="alert" class="text-[var(--app-danger)]">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="share-expiry" class="label">Batas waktu (opsional)</label>
            <input id="share-expiry" type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="field" aria-describedby="share-expiry-help">
            <p id="share-expiry-help" class="help-text">Zona waktu {{ config('app.timezone') }}. Harus di masa depan, maksimal {{ $maxExpiryDays }} hari. Kosong berarti berlaku sampai dicabut. Memberi izin lagi mengganti izin lama dengan identitas izin baru.</p>
            @error('expires_at')<p role="alert" class="text-[var(--app-danger)]">{{ $message }}</p>@enderror
        </div>
        <button type="submit" class="btn btn-primary">Berikan izin baca</button>
    </form>
    <section aria-labelledby="share-list-title" class="space-y-3">
        <h2 id="share-list-title" class="text-h3">Riwayat penerima</h2>
        @forelse ($shares as $share)
            <article class="rounded-lg border border-[var(--app-border)] p-4">
                <p class="font-medium">{{ $share->recipient?->name }} · {{ $share->recipient?->email }}</p>
                <p>{{ $share->revoked_at ? 'Dicabut' : ($share->isActive() ? 'Aktif · baca saja' : 'Kedaluwarsa') }} · Batas: {{ $share->expires_at?->format('d M Y H:i') ?? 'Tidak ada' }}</p>
                @if (! $share->revoked_at)
                    <form method="POST" action="{{ route('workspace.shares.revoke', [$file->public_id, $share->public_id]) }}" class="mt-2">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-secondary" aria-label="Cabut izin {{ $share->recipient?->email }}">Cabut izin</button>
                    </form>
                @endif
            </article>
        @empty
            <p>Belum ada izin berbagi.</p>
        @endforelse
        {{ $shares->links() }}
    </section>
</div>
@endsection
