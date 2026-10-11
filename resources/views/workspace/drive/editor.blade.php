@extends('components.app-shell')

@section('title', 'Workspace · Editor dokumen')
@section('page-title', 'Editor dokumen')
@section('page-description', $file->name)

@section('content')
<div class="mx-auto max-w-7xl space-y-4">
    <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-4 sm:p-5"
             aria-labelledby="collabora-launch-title">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <h2 id="collabora-launch-title" class="truncate text-h3 font-semibold text-[var(--app-text)]">{{ $file->name }}</h2>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">Perubahan disimpan sebagai versi baru di Drive pribadi Anda.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ $backUrl }}" class="btn btn-secondary min-h-10">Kembali ke Drive</a>
                <form method="POST" action="{{ $actionUrl }}" target="collabora-editor">
                    <input type="hidden" name="WOPISrc" value="{{ $wopiSrc }}">
                    <input type="hidden" name="access_token" value="{{ $accessToken }}">
                    <input type="hidden" name="access_token_ttl" value="{{ $accessTokenTtl }}">
                    <button type="submit" class="btn btn-primary min-h-10">Buka editor</button>
                </form>
                <form method="POST" action="{{ route('workspace.drive.files.wopi-session.revoke', [$file, $sessionId]) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary min-h-10">Tutup editor dan cabut sesi</button>
                </form>
            </div>
        </div>
    </section>

    <iframe id="collabora-editor" name="collabora-editor" src="about:blank"
            title="Editor Collabora untuk {{ $file->name }}"
            referrerpolicy="no-referrer"
            allow="clipboard-read; clipboard-write; fullscreen"
            allowfullscreen
            class="h-[76vh] min-h-[32rem] w-full rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)]">
        <p>Editor dokumen tidak didukung oleh peramban ini. <a href="{{ $backUrl }}">Kembali ke Drive</a>.</p>
    </iframe>
</div>
@endsection
