@extends('components.app-shell')
@section('title', 'Workspace · Design')
@section('page-title', 'Design saya')
@section('page-description', 'Proyek pribadi sekolah aktif, dengan editor OpenDesign dan ekspor PNG/PDF.')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <section class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-6" aria-labelledby="new-design">
        <h2 id="new-design" class="text-h3 font-semibold">Buat desain</h2>
        <p class="mt-2 text-small text-[var(--app-text-muted)]">Mulai dengan kanvas persegi. Tambahkan teks, bentuk, dan gambar Drive pribadi yang sudah lolos pemindaian.</p>
        <form method="POST" action="{{ route('workspace.design.store', [], false) }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="design-name" class="label">Nama proyek</label>
                <input id="design-name" name="name" class="field w-full" maxlength="180" required value="{{ old('name') }}" @error('name') aria-invalid="true" aria-describedby="design-name-error" @enderror>
                @error('name')<p id="design-name-error" role="alert" class="mt-1 text-small text-[var(--app-danger)]">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn-primary min-h-11">Buat proyek</button>
        </form>
        <p class="mt-3 text-small text-[var(--app-text-muted)]">Maksimum 100 proyek per akun di sekolah aktif. Proyek tidak dibagikan ke akun lain.</p>
    </section>
    <section aria-labelledby="design-projects">
        <h2 id="design-projects" class="text-h3 font-semibold mb-4">Proyek saya</h2>
        <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($projects as $project)
                <li class="rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-surface)] p-5">
                    <h3 class="font-semibold break-words"><a class="underline focus-visible:outline" href="{{ route('workspace.design.editor', $project->public_id, false) }}">{{ $project->name }}</a></h3>
                    <p class="mt-2 text-small text-[var(--app-text-muted)]">Revisi {{ $project->revision }} · {{ count($project->document['pages']) }} halaman</p>
                    <p class="mt-1 text-small text-[var(--app-text-muted)]">{{ $project->document['width'] }} × {{ $project->document['height'] }} px</p>
                </li>
            @empty
                <li class="p-6 text-[var(--app-text-muted)]">Belum ada proyek. Buat proyek pertama di atas.</li>
            @endforelse
        </ul>
        <div class="mt-6">{{ $projects->links() }}</div>
    </section>
</div>
@endsection
