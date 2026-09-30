@extends('components.app-shell')

@section('title', 'Kepegawaian Saya')
@section('page-title', 'Kepegawaian Saya')
@section('page-description', 'Data hubungan kerja Anda')

@section('content')

<div class="max-w-2xl space-y-4">

    {{--
        The common case, and it is not an error.

        Phase 3 created `employees` and deliberately did not backfill it, so a
        school that has just upgraded has staff accounts and no HR records. A
        login is not proof of employment, and telling this person that the
        record "does not exist" would be both wrong and alarming — what
        actually needs to happen is that the office keys it in.
    --}}
    @if (! $employee)
        <x-card>
            <x-empty-state icon="briefcase"
                           title="Belum ada catatan kepegawaian"
                           description="{{ $isStaff
                               ? 'Akun Anda terdaftar sebagai staff, tetapi datanya belum dimasukkan oleh pengelola sekolah. Data ini diisi dari sisi sekolah, bukan oleh Anda sendiri.'
                               : 'Akun Anda bukan akun staff, jadi tidak ada catatan kepegawaian yang perlu ditampilkan.' }}" />
        </x-card>
    @else
        <x-card title="Jabatan" icon="briefcase">
            <dl class="grid sm:grid-cols-2 gap-4">
                <div>
                    <dt class="text-caption text-[var(--app-text-subtle)]">Nomor induk</dt>
                    <dd class="font-mono text-[var(--app-text)]">{{ $employee->employee_number ?? '—' }}</dd>
                </div>
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
                <div>
                    <dt class="text-caption text-[var(--app-text-subtle)]">Tanggal masuk</dt>
                    <dd class="text-[var(--app-text)]">{{ $employee->hire_date?->format('d F Y') ?? '—' }}</dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Kontrak" icon="calendar">
            <dl class="grid sm:grid-cols-2 gap-4">
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
            </dl>
        </x-card>

        @if ($employee->isContractExpired())
            <x-alert variant="warning"
                     title="Kontrak Anda sudah berakhir"
                     message="Hubungi pengelola sekolah bila ada perpanjangan. Data di atas tidak dapat Anda ubah sendiri." />
        @endif
    @endif

    {{--
        Read-only, and it says so. An employee who can edit their own contract
        can also edit their own position and their own hire date, which turns a
        record the school is accountable for into a field.
    --}}
    <p class="text-caption text-[var(--app-text-subtle)]">
        Halaman ini hanya untuk dibaca. Perubahan data kepegawaian dilakukan
        oleh pengelola sekolah.
    </p>
</div>
@endsection
