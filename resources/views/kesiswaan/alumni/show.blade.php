@extends('components.app-shell')

@section('title', $alumnus->student?->full_name ?? 'Alumni')
@section('page-title', $alumnus->student?->full_name ?? 'Alumni')
@section('page-description', 'Lulus tahun '.$alumnus->graduation_year)

@section('page-actions')
    <a href="{{ route('alumni.index') }}" class="btn btn-secondary">
        <x-icon name="arrow-left" class="w-4 h-4" />
        Kembali
    </a>
@endsection

@section('content')

<div class="max-w-3xl space-y-4">
    <x-alert variant="info"
             title="Data alumni hanya dapat dibaca"
             message="Kelulusan dicatat oleh proses akademik yang menutup enrollment. Halaman ini tidak menyediakan perubahan, dan server juga menolaknya." />

    <x-card title="Data Kelulusan" icon="check-circle">
        <dl class="grid sm:grid-cols-2 gap-4">
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Tahun lulus</dt>
                <dd class="text-[var(--app-text)]">{{ $alumnus->graduation_year }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Tanggal lulus</dt>
                <dd class="text-[var(--app-text)]">
                    {{ $alumnus->graduation_date?->format('d F Y') ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Kelas terakhir</dt>
                <dd class="text-[var(--app-text)]">{{ $alumnus->lastClassroom?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-caption text-[var(--app-text-subtle)]">Jurusan</dt>
                <dd class="text-[var(--app-text)]">{{ $alumnus->department?->name ?? '—' }}</dd>
            </div>
        </dl>

        @if ($alumnus->student)
            <p class="text-caption text-[var(--app-text-subtle)] mt-4">
                NISN {{ $alumnus->student->nisn }}
                @if ($alumnus->student->user)
                    · <a href="{{ route('kesiswaan.students.show', $alumnus->student) }}" class="underline">Lihat data siswa</a>
                @endif
            </p>
        @endif
    </x-card>

    {{--
        The enrollment history. This is what makes the screen worth having: the
        alumni row says someone graduated, and this says what they actually took
        and which classes they sat in.
    --}}
    <x-card title="Riwayat Kelas" icon="history">
        @forelse ($enrollments as $enrollment)
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 {{ ! $loop->first ? 'border-t border-[var(--app-border)]' : '' }}">
                <div class="min-w-0">
                    <p class="font-medium text-[var(--app-text)]">
                        {{ $enrollment->classroom?->name ?? 'Kelas tidak tercatat' }}
                    </p>
                    <p class="text-caption text-[var(--app-text-subtle)]">
                        {{ $enrollment->academicYear?->name ?? '—' }}
                    </p>
                </div>
                <x-status-badge :status="$enrollment->status" />
            </div>
        @empty
            <p class="text-[var(--app-text-subtle)]">Tidak ada riwayat enrollment tercatat.</p>
        @endforelse
    </x-card>

    @if (filled($alumnus->notes))
        <x-card title="Catatan" icon="file-text">
            <p class="text-body text-[var(--app-text-muted)] whitespace-pre-line">{{ $alumnus->notes }}</p>
        </x-card>
    @endif
</div>
@endsection
