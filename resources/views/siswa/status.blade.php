@extends('components.app-shell')

@section('title', 'Status Verifikasi')
@section('page-title', 'Status Verifikasi')
@section('page-description', 'Riwayat pemeriksaan berkas oleh admin sekolah')

@section('content')

<div class="grid lg:grid-cols-3 gap-4 sm:gap-5">
    <div class="lg:col-span-2 space-y-4 sm:space-y-5">

        {{-- Current status --}}
        <x-card title="Status Saat Ini" icon="clipboard-check">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <span @class([
                    'shrink-0 grid place-items-center w-14 h-14 rounded-[var(--radius-lg)]',
                    'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $registration->status === 'verified',
                    'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'     => in_array($registration->status, ['revision', 'rejected']),
                    'bg-[var(--app-warning-soft)] text-[oklch(0.5_0.12_70)]'  => in_array($registration->status, ['pending', 'submitted']),
                    'bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]' => $registration->status === 'draft',
                ])>
                    <x-icon name="{{ $registration->status === 'verified' ? 'check-circle' : 'clock' }}" class="w-7 h-7" />
                </span>
                <div class="min-w-0 flex-1">
                    <x-status-badge :status="$registration->status" />
                    @if ($registration->verified_at)
                        <p class="mt-1.5 text-small text-[var(--app-text-muted)]">
                            Diverifikasi {{ $registration->verified_at->translatedFormat('d F Y H:i') }}
                            @if ($registration->verifier)
                                oleh {{ $registration->verifier->name }}
                            @endif
                        </p>
                    @elseif ($registration->submitted_at)
                        <p class="mt-1.5 text-small text-[var(--app-text-muted)]">
                            Dikirim {{ $registration->submitted_at->translatedFormat('d F Y H:i') }}
                        </p>
                    @endif
                </div>
            </div>

            @if ($registration->admin_note)
                <div class="mt-4 rounded-[var(--radius-md)] border border-[oklch(0.85_0.09_80)] bg-[var(--app-warning-soft)] px-3.5 py-3">
                    <p class="text-caption font-semibold text-[oklch(0.45_0.11_70)] mb-0.5">Catatan admin</p>
                    <p class="text-small text-[oklch(0.4_0.09_70)]">{{ $registration->admin_note }}</p>
                </div>
            @endif

            <div class="mt-4">
                <a href="{{ route('siswa.documents') }}" class="btn btn-secondary">
                    <x-icon name="files" class="w-4 h-4" />
                    Kelola dokumen
                </a>
            </div>
        </x-card>

        {{-- Timeline --}}
        <x-card title="Riwayat Verifikasi" icon="history"
                description="Setiap tindakan admin tercatat lengkap dengan waktu dan alasan.">
            @php $history = $registration->verifications->sortByDesc('created_at'); @endphp

            @if ($history->isEmpty())
                <x-empty-state icon="history" compact
                              title="Belum ada riwayat"
                              description="Riwayat akan muncul setelah admin memeriksa pendaftaran Anda." />
            @else
                <ol class="relative">
                    @foreach ($history as $i => $v)
                        @php
                            [$tone, $icon] = match ($v->action) {
                                'approve' => ['success', 'check-circle'],
                                'reject'  => ['danger', 'x'],
                                'revise'  => ['warning', 'alert-triangle'],
                                'submit'  => ['info', 'upload'],
                                default   => ['neutral', 'info'],
                            };
                            $isLast = $loop->last;
                        @endphp
                        <li class="relative flex gap-3.5 pb-6 {{ $isLast ? 'pb-0' : '' }}">
                            @unless ($isLast)
                                <span class="absolute left-[15px] top-9 bottom-0 w-px bg-[var(--app-border)]" aria-hidden="true"></span>
                            @endunless

                            <span @class([
                                'relative z-10 shrink-0 grid place-items-center w-8 h-8 rounded-full',
                                'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $tone === 'success',
                                'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'   => $tone === 'danger',
                                'bg-[var(--app-warning-soft)] text-[oklch(0.5_0.12_70)]' => $tone === 'warning',
                                'bg-[var(--app-info-soft)] text-[var(--app-info)]'       => $tone === 'info',
                                'bg-[var(--app-surface-muted)] text-[var(--app-text-muted)]' => $tone === 'neutral',
                            ])>
                                <x-icon :name="$icon" class="w-4 h-4" />
                            </span>

                            <div class="min-w-0 flex-1 pt-1">
                                <p class="text-small font-semibold text-[var(--app-text)]">{{ $v->actionLabel() }}</p>
                                <p class="text-caption text-[var(--app-text-muted)] mt-0.5">
                                    {{ $v->created_at?->translatedFormat('d F Y \p\e\k\u\l H:i') }}
                                    @if ($v->admin)
                                        · oleh {{ $v->admin->name }}
                                    @else
                                        · oleh Anda
                                    @endif
                                    @if ($v->document)
                                        · {{ $v->document->documentType?->name }}
                                    @endif
                                </p>
                                @if ($v->note)
                                    <p class="mt-2 text-small text-[var(--app-text-muted)] rounded-[var(--radius-md)] bg-[var(--app-surface-muted)] px-3 py-2">
                                        {{ $v->note }}
                                    </p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-card>
    </div>

    {{-- Side rail: per-document status --}}
    <div>
        <x-card title="Status Dokumen" icon="files" body-class="p-0 sm:p-0">
            <ul class="divide-y divide-[var(--app-border)]">
                @forelse ($registration->documents->sortBy(fn ($d) => $d->documentType?->sort_order ?? 99) as $doc)
                    <li class="px-4 sm:px-5 py-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-small font-medium text-[var(--app-text)] truncate">{{ $doc->documentType?->name }}</p>
                            <x-status-badge :status="$doc->status" class="shrink-0" />
                        </div>

                        @if ($doc->rejection_reason)
                            <p class="mt-1.5 text-caption text-[var(--app-danger)]">{{ $doc->rejection_reason }}</p>
                        @endif

                        @if ($doc->status !== 'missing')
                            <p class="mt-1 text-caption text-[var(--app-text-subtle)]">
                                {{ $doc->reviewed_at ? 'Diperiksa '.$doc->reviewed_at->translatedFormat('d M Y') : 'Diunggah '.$doc->uploaded_at?->translatedFormat('d M Y') }}
                            </p>
                        @endif
                    </li>
                @empty
                    <li class="px-5 py-8">
                        <x-empty-state icon="files" compact title="Belum ada dokumen" />
                    </li>
                @endforelse
            </ul>
        </x-card>
    </div>
</div>
@endsection
