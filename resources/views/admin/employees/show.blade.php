@extends('components.app-shell')

@section('title', 'Detail Kepegawaian')
@section('page-title', $employee->displayName())
@section('page-description', $employee->position ?? 'Jabatan belum diisi')

@section('page-actions')
    @can('employee.update')
        <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-primary">
            <x-icon name="settings" class="w-4 h-4" />
            Ubah
        </a>
    @endcan
    <a href="{{ route('admin.employees') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif
@if (session('error'))
    <x-alert variant="danger" class="mb-4" :message="session('error')" />
@endif

<div class="max-w-2xl space-y-4">
    <x-card title="Identitas" icon="user"
            description="Nama dan email dibaca dari akun pengguna, bukan disalin ke sini.">
        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Nama</dt>
                <dd class="font-semibold text-[var(--app-text)]">{{ $employee->displayName() }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Nomor induk</dt>
                <dd class="font-mono text-[var(--app-text)]">{{ $employee->employee_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Email</dt>
                <dd class="text-[var(--app-text-muted)]">{{ $employee->user?->email ?? 'Belum punya akun' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Role akun</dt>
                <dd class="flex flex-wrap gap-1 mt-0.5">
                    @forelse ($employee->user?->roles ?? [] as $role)
                        <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
                    @empty
                        <span class="text-[var(--app-text-subtle)]">—</span>
                    @endforelse
                </dd>
            </div>
        </dl>

        @if ($employee->user)
            <a href="{{ route('admin.users.show', $employee->user) }}" class="btn btn-secondary mt-4">
                <x-icon name="link" class="w-4 h-4" />
                Lihat akun pengguna
            </a>
        @else
            <x-alert variant="info" class="mt-4"
                     title="Belum ada akun login"
                     message="Hubungan kerja dicatat lebih dulu; akun bisa dibuat belakangan." />
        @endif
    </x-card>

    <x-card title="Jabatan & Kontrak" icon="briefcase">
        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Jabatan</dt>
                <dd class="text-[var(--app-text)]">{{ $employee->position ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Unit kerja</dt>
                <dd class="text-[var(--app-text)]">{{ $employee->department?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Jenis hubungan kerja</dt>
                <dd class="text-[var(--app-text)]">{{ ucfirst(str_replace('_', ' ', $employee->employment_type)) }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Status</dt>
                <dd class="mt-0.5"><x-status-badge :status="$employee->employment_status" /></dd>
            </div>
        </dl>
    </x-card>

    <x-card title="Tanggal" icon="calendar">
        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Tanggal masuk</dt>
                <dd class="text-[var(--app-text)]">{{ $employee->hire_date?->format('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Mulai kontrak</dt>
                <dd class="text-[var(--app-text)]">{{ $employee->contract_start?->format('d F Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Berakhir kontrak</dt>
                <dd class="text-[var(--app-text)]">
                    {{ $employee->contract_end?->format('d F Y') ?? 'Tidak berkontrak' }}
                    @if ($employee->isContractExpired())
                        <span class="badge badge-warning ml-1">Sudah habis</span>
                    @endif
                </dd>
            </div>
            @if ($employee->resigned_at)
                <div>
                    <dt class="text-caption text-[var(--app-text-subtle)]">Berhenti</dt>
                    <dd class="text-[var(--app-danger)]">{{ $employee->resigned_at->format('d F Y') }}</dd>
                </div>
            @endif
        </dl>

        @if ($employee->resignation_reason)
            <div class="mt-4">
                <dt class="text-caption text-[var(--app-text-subtle)]">Alasan berhenti</dt>
                <dd class="text-[var(--app-text-muted)]">{{ $employee->resignation_reason }}</dd>
            </div>
        @endif

        @if ($employee->work_address)
            <div class="mt-4">
                <dt class="text-caption text-[var(--app-text-subtle)]">Alamat kerja</dt>
                <dd class="text-[var(--app-text-muted)]">{{ $employee->work_address }}</dd>
            </div>
        @endif
    </x-card>

    @if ($employee->notes)
        <x-card title="Catatan" icon="file-text">
            <p class="text-body text-[var(--app-text-muted)] whitespace-pre-line">{{ $employee->notes }}</p>
        </x-card>
    @endif

    @if ($employee->creator)
        <p class="text-caption text-[var(--app-text-subtle)]">
            Dicatat oleh {{ $employee->creator->name }} · {{ $employee->created_at?->format('d F Y H:i') }}
        </p>
    @endif
</div>
@endsection
