@extends('components.app-shell')

@section('title', 'Dokumen')
@section('page-title', 'Dokumen Pendaftaran')
@section('page-description', 'Unggah dan pantau berkas yang-required untuk verifikasi')

@section('content')

@php $editable = $registration && $registration->isEditableByStudent(); @endphp

@if (! $editable)
    <x-alert variant="info" class="mb-5" title="Dokumen sedang dikunci"
             message="Pendaftaran Anda sedang diverifikasi admin. Anda tidak dapat mengubah berkas untuk sementara." />
@endif

@if ($rejected->isNotEmpty())
    <x-alert variant="error" class="mb-5" title="Ada {{ $rejected->count() }} dokumen yang perlu diunggah ulang">
        <ul class="mt-2 space-y-1.5">
            @foreach ($rejected as $doc)
                <li>
                    <strong>{{ $doc->documentType?->name }}</strong> — {{ $doc->rejection_reason }}
                </li>
            @endforeach
        </ul>
    </x-alert>
@endif

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    @forelse ($types as $type)
        @php $doc = $documents[$type->id] ?? null; @endphp

        <article @class([
            'surface p-5 flex flex-col',
            'border-l-4 border-l-[var(--app-danger)]' => $doc?->status === 'rejected',
        ])>
            <div class="flex items-start justify-between gap-2 mb-3">
                <div class="min-w-0">
                    <h2 class="text-h3 font-semibold text-[var(--app-text)] leading-snug">{{ $type->label }}</h2>
                    @unless ($type->is_required)
                        <span class="text-caption text-[var(--app-text-subtle)]">Opsional</span>
                    @endunless
                </div>
                <x-status-badge :status="$doc?->status ?? 'missing'" class="shrink-0" />
            </div>

            <p class="text-small text-[var(--app-text-muted)] mb-4">{{ $type->description }}</p>

            @if ($doc && $doc->status === 'rejected')
                <div class="mb-4 rounded-[var(--radius-md)] bg-[var(--app-danger-soft)] border border-[var(--app-danger)]/25 px-3.5 py-3">
                    <p class="text-caption font-semibold text-[var(--app-danger)] mb-0.5">Alasan dari admin</p>
                    <p class="text-small text-[var(--app-text)]">{{ $doc->rejection_reason }}</p>
                </div>
            @endif

            @if ($doc && $doc->status !== 'missing')
                <div class="mb-4 flex items-center gap-3 rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] px-3 py-2.5">
                    <x-icon name="file-text" class="w-5 h-5 text-[var(--app-text-muted)] shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-caption font-medium text-[var(--app-text)] truncate">{{ $doc->original_name }}</p>
                        <p class="text-caption text-[var(--app-text-subtle)]">
                            {{ $doc->size_kb }} KB · {{ $doc->uploaded_at?->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <a href="{{ $doc->url() }}" target="_blank" rel="noopener"
                       class="shrink-0 grid place-items-center w-8 h-8 rounded-[var(--radius-md)] text-[var(--app-text-muted)] hover:bg-[var(--app-surface)] transition-colors"
                       aria-label="Lihat {{ $type->label }}">
                        <x-icon name="eye" class="w-4 h-4" />
                    </a>
                </div>
            @endif

            @if ($editable)
                <form method="POST" action="{{ route('siswa.documents.upload', $type) }}" enctype="multipart/form-data"
                      class="mt-auto"
                      x-data="{
                          file: null, name: '', dragging: false, busy: false, confirmDelete: false,
                          pick(e) { this.file = e.target.files[0] || null; this.name = this.file ? this.file.name : ''; }
                      }"
                      @submit="busy = true">

                    @csrf

                    <label class="block border-2 border-dashed rounded-[var(--radius-md)] p-4 text-center cursor-pointer transition-colors"
                        :class="dragging ? 'border-brand-500 bg-brand-50' : 'border-[var(--app-border-strong)] hover:border-brand-400 hover:bg-[var(--app-primary-soft)]'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false; $refs.input.files = $event.dataTransfer.files; pick($event)">

                        <input x-ref="input" type="file" name="file" required
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               class="sr-only"
                               @change="pick($event)">

                        <x-icon name="upload" class="w-6 h-6 mx-auto text-[var(--app-text-subtle)] mb-1.5" />
                        <p class="text-small font-medium text-[var(--app-text)]">
                            <span x-text="name || '{{ $doc && $doc->status !== 'missing' ? 'Ganti berkas' : 'Pilih berkas' }}'">Pilih berkas</span>
                        </p>
                        <p class="text-caption text-[var(--app-text-subtle)] mt-0.5">
                            JPG, PNG, atau PDF · maks {{ number_format($type->max_size_kb) }} KB
                        </p>
                    </label>

                    <div class="mt-2.5 flex gap-2">
                        <button type="submit" class="btn btn-primary flex-1 justify-center" :disabled="busy">
                            <x-icon name="upload" class="w-4 h-4" />
                            <span x-text="busy ? 'Mengunggah…' : '{{ $doc && $doc->status !== 'missing' ? 'Ganti' : 'Unggah' }}'">Unggah</span>
                        </button>

                        @if ($doc && $doc->status !== 'missing')
                            <button type="button" class="btn btn-secondary shrink-0"
                                    @click="confirmDelete = !confirmDelete"
                                    :aria-expanded="confirmDelete ? 'true' : 'false'"
                                    aria-label="Hapus {{ $type->label }}">
                                <x-icon name="trash" class="w-4 h-4" />
                            </button>
                        @endif
                    </div>
                </form>

                @if ($doc && $doc->status !== 'missing')
                    {{-- Two-step confirm, inline: no native confirm() dialog. --}}
                    <div x-show="confirmDelete" x-cloak x-transition class="mt-2.5 rounded-[var(--radius-md)] border border-[var(--app-danger)]/30 bg-[var(--app-danger-soft)] px-3 py-2.5">
                        <p class="text-caption text-[var(--app-danger)] mb-2">
                            Hapus <strong>{{ $type->label }}</strong>? Anda harus mengunggah ulang.
                        </p>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('siswa.documents.delete', $type) }}" class="flex-1">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm w-full justify-center">Ya, hapus</button>
                            </form>
                            <button type="button" class="btn btn-secondary btn-sm flex-1 justify-center"
                                    @click="confirmDelete = false">Batal</button>
                        </div>
                    </div>
                @endif
            @else
                <p class="text-caption text-[var(--app-text-subtle)] mt-auto">
                    Berkas tidak dapat diubah selama verifikasi berlangsung.
                </p>
            @endif
        </article>
    @empty
        <div class="sm:col-span-2 xl:col-span-3">
            <x-card>
                <x-empty-state icon="files" title="Belum ada jenis dokumen"
                              description="Admin sekolah belum mengatur dokumen yang perlu diunggah." />
            </x-card>
        </div>
    @endforelse
</div>

@if ($editable)
    <x-card class="mt-5" title="Kirim Pendaftaran" icon="send">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0 flex-1">
                <p class="text-body font-semibold text-[var(--app-text)]">Semua berkas sudah lengkap?</p>
                <p class="text-small text-[var(--app-text-muted)] mt-0.5">
                    Setelah dikirim, admin akan memeriksa dan Anda bisa memantau statusnya di dashboard.
                </p>
            </div>
            <form method="POST" action="{{ route('siswa.submit') }}" class="shrink-0"
                  x-data="{ busy: false }" @submit="busy = true">
                @csrf
                <button type="submit" class="btn btn-success w-full sm:w-auto justify-center" :disabled="busy">
                    <span x-text="busy ? 'Mengirim…' : 'Kirim untuk diverifikasi'">Kirim untuk diverifikasi</span>
                </button>
            </form>
        </div>
    </x-card>
@endif
@endsection
