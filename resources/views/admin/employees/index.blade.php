@extends('components.app-shell')

@section('title', 'Kepegawaian')
@section('page-title', 'Kepegawaian')
@section('page-description', $employees->total().' catatan hubungan kerja')

@section('page-actions')
    @can('employee.create')
        <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">
            <x-icon name="user-plus" class="w-4 h-4" />
            Tambah Kepegawaian
        </a>
    @endcan
@endsection

@section('content')

@if (session('success'))
    <x-alert variant="success" class="mb-4" :message="session('success')" />
@endif
@if (session('error'))
    <x-alert variant="danger" class="mb-4" :message="session('error')" />
@endif

{{--
    The no-backfill reminder.

    Phase 3 created `employees` and deliberately did not populate it, because
    guessing which of the staff accounts is a teacher and which is a clerk
    writes false HR records that later reach a payslip. So the honest state of
    this screen for a school that has just upgraded is "0 employees, 14 staff
    with no record" — and hiding that would make the list look complete when it
    is the opposite.
--}}
@if ($unrecordedCount > 0)
    <x-alert variant="info" class="mb-4"
             title="{{ $unrecordedCount }} akun staff belum punya catatan kepegawaian"
             message="Data ini sengaja tidak dibuat otomatis saat migrasi: aplikasi tidak bisa memastikan mana yang guru, mana yang klerk. Tambahkan satu per satu lewat tombol di atas." />
@endif

<form method="GET" class="surface p-4 mb-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
    <div class="lg:col-span-2">
        <label for="q" class="sr-only">Cari pegawai</label>
        <div class="relative">
            <x-icon name="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[var(--app-text-subtle)] pointer-events-none" />
            <input id="q" name="q" value="{{ $q }}" class="field pl-9" placeholder="Cari nama, nomor induk, atau jabatan">
        </div>
    </div>
    <div>
        <label for="status" class="sr-only">Status hubungan kerja</label>
        <select id="status" name="status" class="field">
            <option value="">Semua status</option>
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="department" class="sr-only">Unit kerja</label>
        <select id="department" name="department" class="field">
            <option value="">Semua unit</option>
            @foreach ($departments as $id => $name)
                <option value="{{ $id }}" @selected((int) ($filters['department'] ?? 0) === $id)>{{ $name }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex gap-2">
        <button class="btn btn-primary flex-1 justify-center" type="submit">Filter</button>
        <a href="{{ route('admin.employees') }}" class="btn btn-secondary shrink-0" aria-label="Reset">
            <x-icon name="refresh-cw" class="w-4 h-4" />
        </a>
    </div>
</form>

@forelse ($employees as $employee)
    <div class="surface p-4 mb-3 {{ $employee->isCurrent() ? '' : 'opacity-75' }}">
        <div class="flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="{{ route('admin.employees.show', $employee) }}"
                       class="font-semibold text-[var(--app-text)] hover:underline">
                        {{ $employee->displayName() }}
                    </a>
                    <x-status-badge :status="$employee->employment_status" />
                    @if ($employee->isContractExpired())
                        <span class="badge badge-warning">
                            <x-icon name="alert-triangle" class="w-3 h-3" />
                            Kontrak Habis
                        </span>
                    @endif
                </div>

                <p class="text-small text-[var(--app-text-muted)] mt-1">
                    {{ $employee->position ?? 'Jabatan belum diisi' }}
                    @if ($employee->department)
                        · {{ $employee->department->name }}
                    @endif
                </p>

                {{--
                    The account's name/email live on `users` and are shown here
                    READ-ONLY. Nothing on this screen writes them, which is the
                    whole point of keeping employment and identity apart.
                --}}
                @if ($employee->user)
                    <p class="text-caption text-[var(--app-text-subtle)] mt-1.5 flex items-center gap-1.5 flex-wrap">
                        <x-icon name="user" class="w-3.5 h-3.5" />
                        {{ $employee->user->email }}
                        @foreach ($employee->user->roles as $role)
                            <span class="badge badge-neutral">{{ ucfirst(str_replace('_', ' ', $role->name)) }}</span>
                        @endforeach
                    </p>
                @else
                    <p class="text-caption text-[var(--app-warning)] mt-1.5 flex items-center gap-1.5">
                        <x-icon name="alert-triangle" class="w-3.5 h-3.5" />
                        Belum punya akun login
                    </p>
                @endif
            </div>

            <div class="text-caption text-[var(--app-text-muted)] text-right shrink-0 space-y-0.5">
                @if ($employee->employee_number)
                    <p class="font-mono font-semibold text-[var(--app-text)]">{{ $employee->employee_number }}</p>
                @endif
                @if ($employee->hire_date)
                    <p>Masuk {{ $employee->hire_date->format('d M Y') }}</p>
                @endif
                @if ($employee->resigned_at)
                    <p class="text-[var(--app-danger)]">Berhenti {{ $employee->resigned_at->format('d M Y') }}</p>
                @endif
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @can('employee.update')
                    <a href="{{ route('admin.employees.edit', $employee) }}" class="btn btn-secondary">
                        <x-icon name="settings" class="w-4 h-4" />
                        Ubah
                    </a>
                @endcan
                <a href="{{ route('admin.employees.show', $employee) }}" class="btn btn-secondary" aria-label="Detail">
                    <x-icon name="eye" class="w-4 h-4" />
                </a>
            </div>
        </div>
    </div>
@empty
    <x-card>
        <x-empty-state icon="briefcase"
                       title="Belum ada catatan kepegawaian"
                       description="Tambahkan catatan pertama. Nama dan email tetap dibaca dari akun pengguna, jadi tidak ada duplikasi." />
        @can('employee.create')
            <div class="flex justify-center mt-4">
                <a href="{{ route('admin.employees.create') }}" class="btn btn-primary">
                    <x-icon name="user-plus" class="w-4 h-4" />
                    Tambah Kepegawaian
                </a>
            </div>
        @endcan
    </x-card>
@endforelse

@if ($employees->hasPages())
    <div class="mt-4">{{ $employees->links() }}</div>
@endif
@endsection
