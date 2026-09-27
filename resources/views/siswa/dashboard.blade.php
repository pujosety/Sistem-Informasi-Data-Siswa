@extends('components.app-shell')

@section('title', 'Dashboard')
@section('page-title', 'Halo, '.$student->full_name)
@section('page-description', 'Pendaftaran '.($registration?->academicYear?->name ?? 'siswa'))

@section('page-actions')
    <x-status-badge :status="$registration?->status ?? 'draft'" />
@endsection

@section('content')
@php
    // What does this student need to do next?
    $needsAction = $registration && in_array($registration->status, [
        \App\Models\Registration::STATUS_DRAFT, \App\Models\Registration::STATUS_REVISION,
    ], true);

    $progress = [
        ['label' => 'Data Pribadi', 'done' => filled($student->birth_date) && filled($student->city) && filled($student->religion), 'url' => route('siswa.wizard', ['step' => 'pribadi'])],
        ['label' => 'Orang Tua / Wali', 'done' => $student->parents()->whereIn('relation', ['father', 'mother'])->count() === 2, 'url' => route('siswa.wizard', ['step' => 'orang-tua'])],
        ['label' => 'Pendidikan', 'done' => filled($student->previous_school) && filled($student->graduation_year), 'url' => route('siswa.wizard', ['step' => 'pendidikan'])],
        ['label' => 'Dokumen', 'done' => $types->isNotEmpty() && $types->filter(fn ($t) => ($documents[$t->id] ?? null) && $documents[$t->id]->status !== 'missing')->count() === $types->count(), 'url' => route('siswa.documents')],
        ['label' => 'Verifikasi', 'done' => $registration?->status === \App\Models\Registration::STATUS_VERIFIED, 'url' => route('siswa.status')],
    ];
    $doneCount = collect($progress)->where('done', true)->count();
    $pct = (int) round($registration?->completeness ?? 0);
@endphp

@if ($rejected->isNotEmpty())
    {{-- ACTION REQUIRED takes top priority --}}
    <x-card class="border-l-4 !border-l-[var(--app-danger)] mb-5">
        <div class="flex flex-col sm:flex-row sm:items-start gap-4">
            <span class="shrink-0 grid place-items-center w-11 h-11 rounded-[var(--radius-md)] bg-[var(--app-danger-soft)] text-[var(--app-danger)]">
                <x-icon name="alert-triangle" class="w-5 h-5" />
            </span>
            <div class="min-w-0 flex-1">
                <h2 class="text-h2 font-bold text-[var(--app-text)]">Tindakan diperlukan</h2>
                <p class="mt-0.5 text-body text-[var(--app-text-muted)]">
                    {{ $rejected->count() }} dokumen perlu diunggah ulang agar pendaftaran dapat diverifikasi.
                </p>

                <ul class="mt-4 space-y-2">
                    @foreach ($rejected as $doc)
                        <li class="rounded-[var(--radius-md)] bg-[var(--app-danger-soft)] border border-[var(--app-danger)]/20 px-3.5 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-small font-semibold text-[var(--app-text)]">{{ $doc->documentType?->name }}</p>
                                <span class="badge badge-danger">
                                    <x-icon name="x" class="w-3 h-3" />
                                    Ditolak
                                </span>
                            </div>
                            <p class="mt-1 text-caption text-[var(--app-text-muted)]">
                                <span class="font-semibold">Alasan dari admin:</span> {{ $doc->rejection_reason }}
                            </p>
                            <a href="{{ route('siswa.documents') }}" class="btn btn-sm btn-primary mt-2.5">
                                <x-icon name="upload" class="w-3.5 h-3.5" />
                                Unggah ulang
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-card>
@endif

<div class="grid lg:grid-cols-3 gap-4 sm:gap-5">

    {{-- ================= Left / main column ================= --}}
    <div class="lg:col-span-2 space-y-4 sm:space-y-5">

        {{-- Progress --}}
        <x-card title="Progres Pendaftaran" icon="chart" description="Kelengkapan keseluruhan data Anda">
            <x-slot:actions>
                <span class="text-h2 font-bold tabular-nums {{ $pct >= 100 ? 'text-[var(--app-success)]' : 'text-[var(--app-primary)]' }}">{{ $pct }}%</span>
            </x-slot:actions>

            <div class="progress-track" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"
                 aria-label="Kelengkapan pendaftaran {{ $pct }} persen">
                <div class="progress-fill" style="width: {{ max(2, $pct) }}%"></div>
            </div>

            <ol class="mt-5 space-y-1">
                @foreach ($progress as $i => $step)
                    <li>
                        <a href="{{ $step['url'] }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius-md)] transition-colors hover:bg-[var(--app-surface-muted)] group">
                            <span @class([
                                'shrink-0 grid place-items-center w-7 h-7 rounded-full text-caption font-bold transition-colors',
                                'bg-[var(--app-success)] text-white' => $step['done'],
                                'bg-brand-100 text-brand-700' => ! $step['done'] && $i === $doneCount,
                                'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' => ! $step['done'] && $i !== $doneCount,
                            ])>
                                @if ($step['done'])
                                    <x-icon name="check" class="w-3.5 h-3.5" />
                                @else
                                    {{ $i + 1 }}
                                @endif
                            </span>

                            <span class="text-body {{ $step['done'] ? 'text-[var(--app-text)]' : 'font-medium text-[var(--app-text)]' }}">{{ $step['label'] }}</span>

                            @if ($step['done'])
                                <span class="ml-auto text-caption font-semibold text-[var(--app-success)]">Selesai</span>
                            @elseif ($i === $doneCount && $needsAction)
                                <span class="ml-auto badge badge-brand">Lanjutkan</span>
                            @else
                                <span class="ml-auto text-caption text-[var(--app-text-subtle)]">Belum</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ol>
        </x-card>

        {{-- Document checklist --}}
        <x-card title="Status Dokumen" icon="files" body-class="p-0 sm:p-0">
            <x-slot:actions>
                <a href="{{ route('siswa.documents') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Kelola</a>
            </x-slot:actions>

            <ul class="divide-y divide-[var(--app-border)]">
                @forelse ($types as $type)
                    @php $doc = $documents[$type->id] ?? null; @endphp
                    <li class="flex items-center gap-3 px-4 sm:px-5 py-3">
                        <span @class([
                            'shrink-0 grid place-items-center w-9 h-9 rounded-[var(--radius-md)]',
                            'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $doc?->status === 'valid',
                            'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'   => $doc?->status === 'rejected',
                            'bg-[var(--app-warning-soft)] text-[oklch(0.5_0.12_70)]' => $doc?->status === 'pending',
                            'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' => !$doc || $doc->status === 'missing',
                        ])>
                            <x-icon name="{{ $doc?->status === 'valid' ? 'check-circle' : ($doc?->status === 'rejected' ? 'x' : 'file-text') }}" class="w-[18px] h-[18px]" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-small font-semibold text-[var(--app-text)] truncate">{{ $type->label }}</p>
                            <p class="text-caption text-[var(--app-text-muted)] truncate">
                                {{ $doc && $doc->status !== 'missing' ? $doc->original_name : $type->description }}
                            </p>
                        </div>

                        @if ($doc && $doc->status === 'valid')
                            <a href="{{ $doc->url() }}" target="_blank" rel="noopener"
                               class="shrink-0 hidden sm:grid place-items-center w-8 h-8 rounded-[var(--radius-md)] text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)]"
                               aria-label="Lihat {{ $type->label }}">
                                <x-icon name="eye" class="w-4 h-4" />
                            </a>
                        @endif
                        <x-status-badge :status="$doc?->status ?? 'missing'" class="shrink-0" />
                    </li>
                @empty
                    <li class="px-5 py-8">
                        <x-empty-state icon="files" compact
                                      title="Belum ada jenis dokumen"
                                      description="Admin sekolah belum mengatur dokumen yang perlu diunggah." />
                    </li>
                @endforelse
            </ul>
        </x-card>
    </div>

    {{-- ================= Right rail ================= --}}
    <div class="space-y-4 sm:space-y-5">

        {{-- Next action --}}
        <x-card title="Langkah selanjutnya" icon="sparkles">
            <p class="text-body text-[var(--app-text-muted)]">{{ $nextStep }}</p>

            @if ($registration?->admin_note)
                <div class="mt-4 rounded-[var(--radius-md)] border border-[oklch(0.85_0.09_80)] bg-[var(--app-warning-soft)] px-3.5 py-3">
                    <p class="text-caption font-semibold text-[oklch(0.45_0.11_70)] mb-0.5">Catatan admin</p>
                    <p class="text-small text-[oklch(0.4_0.09_70)]">{{ $registration->admin_note }}</p>
                </div>
            @endif

            <div class="mt-4 space-y-2">
                @if ($needsAction)
                    <a href="{{ route('siswa.wizard') }}" class="btn btn-primary w-full justify-center">
                        <x-icon name="list-checks" class="w-4 h-4" />
                        {{ $rejected->isNotEmpty() ? 'Perbaiki sekarang' : 'Lanjutkan pendaftaran' }}
                    </a>
                @elseif ($registration?->status === \App\Models\Registration::STATUS_PENDING)
                    <a href="{{ route('siswa.documents') }}" class="btn btn-secondary w-full justify-center">
                        <x-icon name="files" class="w-4 h-4" />
                        Lihat dokumen
                    </a>
                @elseif ($registration?->status === \App\Models\Registration::STATUS_VERIFIED)
                    <a href="{{ route('siswa.status') }}" class="btn btn-secondary w-full justify-center">
                        <x-icon name="history" class="w-4 h-4" />
                        Riwayat verifikasi
                    </a>
                @endif
            </div>
        </x-card>

        {{-- Identity summary --}}
        <x-card title="Data Anda" icon="user">
            <dl class="space-y-3 text-small">
                @foreach ([
                    'NISN' => $student->nisn,
                    'Nama' => $student->full_name,
                    'Jenis Kelamin' => $student->genderLabel(),
                    'Tempat, Tgl Lahir' => trim(($student->birth_place ?? '-').', '.($student->birth_date?->format('d M Y') ?? '-'), ', '),
                    'Asal Sekolah' => $student->previous_school ?? '-',
                    'Kontak' => $student->phone ?? '-',
                ] as $label => $value)
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)] shrink-0">{{ $label }}</dt>
                        <dd class="text-[var(--app-text)] font-medium text-right break-words">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-card>

        {{-- Verification timeline preview --}}
        @if ($registration?->verifications?->isNotEmpty())
            <x-card title="Aktivitas Terakhir" icon="history">
                <x-slot:actions>
                    <a href="{{ route('siswa.status') }}" class="text-small font-semibold text-[var(--app-primary)] hover:underline">Semua</a>
                </x-slot:actions>

                <ol class="space-y-3">
                    @foreach ($registration->verifications->sortByDesc('created_at')->take(4) as $v)
                        <li class="flex items-start gap-2.5">
                            <span class="shrink-0 mt-1 w-1.5 h-1.5 rounded-full bg-brand-400"></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-small font-medium text-[var(--app-text)]">{{ $v->actionLabel() }}</p>
                                <p class="text-caption text-[var(--app-text-muted)]">
                                    {{ $v->created_at?->translatedFormat('d M Y · H:i') }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </x-card>
        @endif
    </div>
</div>
@endsection
