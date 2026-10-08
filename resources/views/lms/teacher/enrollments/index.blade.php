@extends('components.app-shell')

@section('title', 'LMS · Peserta · '.$course->title)
@section('page-title', 'Peserta Course')
@section('page-description', $course->title.' · '.($course->classroom?->name ?? 'Kelas'))

@section('content')
<div class="space-y-5">
    @if (session('success'))
        <x-alert variant="success" :message="session('success')" />
    @endif

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                <h2 class="mt-1 text-h2 font-semibold">{{ $course->title }}</h2>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">Peserta aktif: {{ $enrollments->total() }}</p>
            </div>
            <a href="{{ route('lms.teacher.courses.show', $course) }}" class="btn btn-secondary btn-sm">Kembali ke course</a>
        </div>
    </x-card>

    <x-card title="Tambah peserta" description="Hanya siswa yang terdaftar pada kelas dan tahun ajaran course ini yang dapat ditambahkan.">
        <form method="POST" action="{{ route('lms.teacher.enrollments.store', $course) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="min-w-0 flex-1">
                <label for="student_id" class="mb-1.5 block text-small font-medium text-[var(--app-text)]">Siswa</label>
                <select id="student_id" name="student_id" class="input" required>
                    <option value="">Pilih siswa</option>
                    @foreach ($availableStudents as $student)
                        <option value="{{ $student->id }}">{{ $student->full_name }}{{ $student->nisn ? ' · '.$student->nisn : '' }}</option>
                    @endforeach
                </select>
                @error('student_id')<p class="mt-1 text-caption text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Tambahkan</button>
        </form>
        @if ($availableStudents->isEmpty())
            <p class="mt-3 text-caption text-[var(--app-text-muted)]">Tidak ada siswa eligible baru dari kelas ini.</p>
        @endif
    </x-card>

    <x-card title="Roster aktif" description="Mengeluarkan peserta hanya menonaktifkan enrollment; riwayat pengumpulan dan nilai tetap aman.">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-small">
                <thead>
                    <tr class="border-b border-[var(--app-border)] text-caption text-[var(--app-text-muted)]">
                        <th class="px-3 py-3">Siswa</th>
                        <th class="px-3 py-3">NISN</th>
                        <th class="px-3 py-3">Terdaftar</th>
                        <th class="px-3 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--app-border)]">
                    @forelse ($enrollments as $enrollment)
                        <tr>
                            <td class="px-3 py-3 font-medium">{{ $enrollment->student?->full_name }}</td>
                            <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ $enrollment->student?->nisn ?: '—' }}</td>
                            <td class="px-3 py-3 text-[var(--app-text-muted)]">{{ $enrollment->enrolled_at?->format('d M Y') }}</td>
                            <td class="px-3 py-3 text-right">
                                <form method="POST" action="{{ route('lms.teacher.enrollments.destroy', [$course, $enrollment]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-secondary btn-sm">Keluarkan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-6 text-center text-[var(--app-text-muted)]">Belum ada peserta aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $enrollments->links() }}</div>
    </x-card>
</div>
@endsection
