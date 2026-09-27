@extends('components.app-shell')

@section('title', $student->full_name)
@section('page-title', $student->full_name)
@section('page-description', 'NISN '.$student->nisn)

@section('page-actions')
    <a href="{{ route('laporan.index', ['class_id' => $student->class_id]) }}" class="btn btn-secondary">
        <x-icon name="file-text" class="w-4 h-4" />
        Laporan kelas ini
    </a>
@endsection

@section('content')

<div class="grid xl:grid-cols-3 gap-4 sm:gap-5">

    {{-- ============ Main column ============ --}}
    <div class="xl:col-span-2 space-y-4 sm:space-y-5">

        {{-- Identity --}}
        <x-card title="Data Pribadi" icon="user">
            <dl class="grid sm:grid-cols-2 gap-x-6 gap-y-3.5 text-body">
                @foreach ([
                    'NISN' => $student->nisn,
                    'NIK' => $student->nik,
                    'Nama Lengkap' => $student->full_name,
                    'Jenis Kelamin' => $student->genderLabel(),
                    'Tempat Lahir' => $student->birth_place,
                    'Tanggal Lahir' => $student->birth_date?->translatedFormat('d F Y'),
                    'Agama' => $student->religion,
                    'Nomor HP' => $student->phone,
                    'Asal Sekolah' => $student->previous_school,
                    'Tahun Lulus' => $student->graduation_year,
                    'Nomor Ijazah' => $student->diploma_number,
                    'Nilai Rata-rata' => $student->previous_score,
                ] as $label => $value)
                    <div>
                        <dt class="text-caption text-[var(--app-text-muted)]">{{ $label }}</dt>
                        <dd class="font-medium text-[var(--app-text)] mt-0.5 break-words">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
                <div class="sm:col-span-2">
                    <dt class="text-caption text-[var(--app-text-muted)]">Alamat</dt>
                    <dd class="font-medium text-[var(--app-text)] mt-0.5">
                        {{ $student->address ?: '—' }}
                        @if ($student->city)
                            <span class="text-[var(--app-text-muted)] font-normal">
                                , {{ collect([$student->village, $student->district, $student->city, $student->province])->filter()->implode(', ') }}
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        {{-- Documents --}}
        <x-card title="Dokumen" icon="files" description="Berkas yang dilampirkan pada pendaftaran">
            @php $documents = $student->registration?->documents ?? collect(); @endphp

            @if ($documents->isEmpty())
                <x-empty-state icon="files" compact
                              title="Belum ada dokumen"
                              description="Siswa belum mengunggah berkas pendukung." />
            @else
                <ul class="divide-y divide-[var(--app-border)] -mx-4 sm:-mx-5">
                    @foreach ($documents->sortBy(fn ($d) => $d->documentType?->sort_order ?? 99) as $doc)
                        <li class="px-4 sm:px-5 py-3.5 flex items-center gap-3">
                            <span @class([
                                'shrink-0 grid place-items-center w-9 h-9 rounded-[var(--radius-md)]',
                                'bg-[var(--app-success-soft)] text-[var(--app-success)]' => $doc->status === 'valid',
                                'bg-[var(--app-danger-soft)] text-[var(--app-danger)]'   => $doc->status === 'rejected',
                                'bg-[var(--app-warning-soft)] text-[oklch(0.5_0.12_70)]' => $doc->status === 'pending',
                                'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' => $doc->status === 'missing',
                            ])>
                                <x-icon :name="$doc->status === 'valid' ? 'check-circle' : 'file-text'" class="w-4 h-4" />
                            </span>

                            <div class="min-w-0 flex-1">
                                <p class="text-small font-medium text-[var(--app-text)]">{{ $doc->documentType?->name }}</p>
                                <p class="text-caption text-[var(--app-text-subtle)]">
                                    {{ $doc->status === 'missing' ? 'Belum diunggah' : $doc->original_name }}
                                </p>
                            </div>

                            <x-status-badge :status="$doc->status" class="shrink-0" />
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        {{-- Verification history --}}
        <x-card title="Riwayat Verifikasi" icon="history"
                description="Riwayat pemeriksaan berkas oleh admin sekolah">
            @php $history = $student->registration?->verifications?->sortByDesc('created_at') ?? collect(); @endphp

            @if ($history->isEmpty())
                <x-empty-state icon="history" compact title="Belum ada riwayat"
                              description="Riwayat akan muncul setelah pendaftaran dikirim." />
            @else
                <ol class="space-y-3">
                    @foreach ($history as $v)
                        <li class="flex items-start gap-3">
                            <span @class([
                                'shrink-0 mt-1.5 w-2 h-2 rounded-full',
                                'bg-[var(--app-success)]'   => $v->action === 'approve',
                                'bg-[var(--app-danger)]'    => $v->action === 'reject',
                                'bg-[oklch(0.62_0.14_75)]'  => $v->action === 'revise',
                                'bg-[var(--app-info)]'      => $v->action === 'submit',
                            ])></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-small font-medium text-[var(--app-text)]">{{ $v->actionLabel() }}</p>
                                <p class="text-caption text-[var(--app-text-muted)]">
                                    {{ $v->admin?->name ?? 'Siswa' }} ·
                                    {{ $v->created_at?->translatedFormat('d M Y H:i') }}
                                    @if ($v->document) · {{ $v->document->documentType?->name }} @endif
                                </p>
                                @if ($v->note)
                                    <p class="mt-1.5 text-small text-[var(--app-text-muted)] bg-[var(--app-surface-muted)] rounded-[var(--radius-md)] px-3 py-2">
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

    {{-- ============ Side rail ============ --}}
    <div class="space-y-4 sm:space-y-5">
        <x-card title="Status Pendaftaran" icon="clipboard-check">
            <x-status-badge :status="$student->status" />

            @if ($registration = $student->registration)
                <dl class="mt-4 space-y-2.5 text-small">
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)]">Kelengkapan</dt>
                        <dd class="font-bold tabular-nums">{{ $registration->completeness }}%</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)]">Tahun Ajaran</dt>
                        <dd class="font-medium">{{ $registration->academicYear?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-[var(--app-text-muted)]">Terdaftar</dt>
                        <dd class="font-medium">{{ $registration->created_at?->translatedFormat('d M Y') }}</dd>
                    </div>
                    @if ($registration->verified_at)
                        <div class="flex justify-between gap-3">
                            <dt class="text-[var(--app-text-muted)]">Diverifikasi</dt>
                            <dd class="font-medium">{{ $registration->verified_at->translatedFormat('d M Y') }}</dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 progress-track">
                    <div class="progress-fill" style="width: {{ max(2, $registration->completeness) }}%"></div>
                </div>
            @endif
        </x-card>

        <x-card title="Kelas & Jurusan" icon="school">
            <dl class="space-y-2.5 text-small">
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--app-text-muted)] shrink-0">Angkatan</dt>
                    <dd class="font-medium">{{ $student->entry_year ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--app-text-muted)] shrink-0">Kelas</dt>
                    <dd class="font-medium text-right">{{ $student->schoolClass?->name ?? 'Belum ditempatkan' }}</dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-[var(--app-text-muted)] shrink-0">Jurusan</dt>
                    <dd class="font-medium text-right">{{ $student->schoolClass?->department?->name ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Orang Tua / Wali" icon="users">
            @forelse ($student->parents as $p)
                <div @class([
                    'flex justify-between gap-3 py-2.5 first:pt-0 last:pb-0',
                    'border-b border-[var(--app-border)]' => ! $loop->last,
                ])>
                    <span class="text-[var(--app-text-muted)] shrink-0">{{ $p->relationLabel() }}</span>
                    <span class="text-right min-w-0">
                        <span class="font-medium text-[var(--app-text)] block break-words">{{ $p->full_name }}</span>
                        <span class="text-caption text-[var(--app-text-muted)]">
                            {{ $p->phone }}@if ($p->job) · {{ $p->job }}@endif
                        </span>
                    </span>
                </div>
            @empty
                <p class="text-small text-[var(--app-text-subtle)]">Data orang tua belum diisi.</p>
            @endforelse
        </x-card>
    </div>
</div>
@endsection
