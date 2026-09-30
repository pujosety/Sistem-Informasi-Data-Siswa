@extends('components.app-shell')

@section('title', 'Nilai '.$classroom->name)
@section('page-title', 'Buku Nilai')
@section('page-description', $classroom->name.($semester ? ' · '.$semester->label : ''))

@section('content')

{{-- --------------------------------------------------------------------------
     Term picker. The term is part of the grade's identity, so it is chosen by
     navigation rather than smuggled in with the save payload.
     -------------------------------------------------------------------------- --}}
<form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
    <div class="min-w-[13rem]">
        <x-form-field name="semester_id" label="Semester" type="select">
            @foreach ($semesters as $option)
                <option value="{{ $option->id }}" @selected($semester && $semester->id === $option->id)>
                    {{ $option->label ?? 'Semester '.$option->name }}
                </option>
            @endforeach
        </x-form-field>
    </div>
    <button type="submit" class="btn btn-secondary">Tampilkan</button>

    <span class="ml-auto text-caption text-[var(--app-text-muted)]">
        {{ $enrollments->count() }} siswa aktif
        @if ($semester)
            · {{ $subjects->count() }} mata pelajaran
        @endif
    </span>
</form>

@if (! $semester)
    <x-alert variant="info" title="Belum ada semester"
             message="Tambahkan kalender semester untuk tahun ajaran ini sebelum mencatat nilai. Baris nilai sudah dikunci pada satu term, jadi tidak ada nilai yang bisa disimpan tanpa term." />
@elseif ($subjects->isEmpty())
    <x-alert variant="info" title="Belum ada mata pelajaran"
             message="Tambahkan mata pelajaran di Master Data sebelum mengisi buku nilai." />
@elseif ($enrollments->isEmpty())
    <x-alert variant="info" title="Belum ada siswa"
             message="Kelas ini belum memiliki siswa aktif, sehingga tidak ada yang bisa dinilai." />
@else

    {{-- The grid: one row per student, one column per subject, one field per
         cell. Saving is PER SUBJECT, so the whole grid is a form per subject
         rather than one giant form — a teacher owns one subject at a time and a
         single 30x8 submit would make a validation failure lose every score. --}}

    <x-card title="Input Nilai" icon="clipboard-check"
            description="Kosongkan sel bila belum dinilai — bukan berarti nol.">
        <div class="overflow-x-auto">
            <table class="data-table min-w-[46rem]">
                <thead>
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-[var(--app-surface)]">Siswa</th>
                        @foreach ($subjects as $subject)
                            <th scope="col" class="text-center">{{ $subject->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($enrollments as $enrollment)
                        @php $student = $enrollment->student; @endphp
                        <tr>
                            <th scope="row" class="sticky left-0 z-10 bg-[var(--app-surface)] text-left font-normal">
                                <span class="block font-medium text-[var(--app-text)]">{{ $student?->full_name }}</span>
                                <span class="block font-mono text-xs text-[var(--app-text-muted)]">{{ $student?->nisn }}</span>
                            </th>

                            @foreach ($subjects as $subject)
                                @php
                                    $grade = $grades->get($subject->id)?->get($enrollment->id);
                                    // A published score is locked against silent
                                    // overwrite by a teacher who does not also
                                    // hold grade.publish: republishing is a
                                    // separate, deliberate act.
                                    $locked = $grade !== null
                                        && $grade->status === \App\Models\Grade::PUBLISHED
                                        && ! auth()->user()->can('publish', [\App\Models\Grade::class, $classroom]);
                                @endphp
                                <td class="text-center">
                                    @if ($locked)
                                        <span class="font-mono text-sm font-semibold text-[var(--app-text)]">
                                            {{ rtrim(rtrim((string) $grade->score, '0'), '.') }}
                                        </span>
                                        <span class="block text-[10px] font-medium uppercase tracking-wide text-[var(--app-success)]">terbit</span>
                                    @else
                                        {{-- One input per cell, named by ENROLLMENT.
                                             The subject is the form's unit, so it
                                             rides along as a hidden field. --}}
                                        <input type="number" step="0.01" min="0" max="100"
                                               name="scores[{{ $enrollment->id }}]"
                                               value="{{ $grade?->score }}"
                                               placeholder="—"
                                               aria-label="Nilai {{ $student?->full_name }} — {{ $subject->name }}"
                                               class="field mx-auto w-20 px-2 py-1 text-center text-sm">
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Per-subject save bar: pick the subject, save that subject only. --}}
        <form method="POST" action="{{ route('academic.grades.store', $classroom) }}"
              class="mt-4 flex flex-wrap items-end gap-2 border-t border-[var(--app-border)] pt-4">
            @csrf
            <input type="hidden" name="semester_id" value="{{ $semester->id }}">

            <div class="min-w-[15rem] flex-1">
                <x-form-field name="subject_id" label="Mata pelajaran" type="select" required>
                    @foreach ($subjects as $subject)
                        <option value="{{ $subject->id }}">{{ $subject->name }}</option>
                    @endforeach
                </x-form-field>
            </div>

            <button type="submit" class="btn btn-primary">
                <x-icon name="save" class="w-4 h-4" /> Simpan Draft
            </button>
        </form>

        <p class="help-text mt-2">
            Menyimpan tidak menerbitkan nilai. Nilai yang tersimpan tetap berstatus draft
            dan tidak terlihat oleh siswa maupun orang tua sampai diterbitkan.
        </p>
    </x-card>

    {{-- Publication is a DIFFERENT permission from entry, so this form simply
         does not exist for a teacher who cannot publish. There is no hidden
         button to reveal and no client-side toggle to bypass — the markup is
         absent, which is what makes grade.publish meaningful. --}}
    @can('publish', [\App\Models\Grade::class, $classroom])
        <x-card title="Terbitkan Nilai" icon="send" class="mt-4"
                description="Menerbitkan membuat nilai terlihat oleh siswa dan orang tua.">
            <form method="POST" action="{{ route('academic.grades.publish', $classroom) }}"
                  class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="hidden" name="semester_id" value="{{ $semester->id }}">

                <fieldset class="flex-1">
                    <legend class="label">Mata pelajaran yang diterbitkan</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($subjects as $subject)
                            @php
                                $drafts = $grades->get($subject->id)
                                    ? $grades->get($subject->id)->filter(fn ($g) => $g->status === \App\Models\Grade::DRAFT && $g->score !== null)
                                    : collect();
                            @endphp
                            <label class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-sm ring-1 ring-[var(--app-border)] hover:bg-[var(--app-surface-muted)]">
                                <input type="checkbox" name="subject_ids[]" value="{{ $subject->id }}"
                                       class="rounded border-[var(--app-border)]">
                                <span>{{ $subject->name }}</span>
                                @if ($drafts->isNotEmpty())
                                    <span class="text-xs text-[var(--app-text-muted)]">{{ $drafts->count() }} draft</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                    @error('subject_ids')
                        <p class="error-text flex items-start gap-1">{{ $message }}</p>
                    @enderror
                </fieldset>

                <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Terbitkan nilai ini ke siswa dan orang tua? Nilai yang sudah terbit tidak lagi bisa diubah guru.')">
                    <x-icon name="send" class="w-4 h-4" /> Terbitkan
                </button>
            </form>
        </x-card>
    @endcan

    {{-- Grade status per subject, so the teacher can see at a glance what is
         still a draft. --}}
    <x-card title="Status per Mata Pelajaran" icon="chart-bar" class="mt-4" bodyClass="p-0">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col">Mata Pelajaran</th>
                    <th scope="col" class="text-center">Draft</th>
                    <th scope="col" class="text-center">Terbit</th>
                    <th scope="col">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subjects as $subject)
                    @php
                        $rows = $grades->get($subject->id) ?? collect();
                        $draftCount = $rows->filter(fn ($g) => $g->status === \App\Models\Grade::DRAFT && $g->score !== null)->count();
                        $publishedCount = $rows->filter(fn ($g) => $g->status === \App\Models\Grade::PUBLISHED)->count();
                        $complete = $publishedCount >= $enrollments->count();
                    @endphp
                    <tr>
                        <td>{{ $subject->name }}</td>
                        <td class="text-center font-mono">{{ $draftCount }}</td>
                        <td class="text-center font-mono">{{ $publishedCount }}</td>
                        <td>
                            @if ($publishedCount === 0)
                                {{-- Not the shared status-badge: its `draft` key
                                     is labelled "Belum Lengkap", which is a
                                     document-state phrase and wrong here. --}}
                                <span class="badge badge-neutral"><x-icon name="clock" class="w-3 h-3" /> Belum ada nilai</span>
                            @elseif ($complete)
                                <span class="badge badge-success"><x-icon name="check-circle" class="w-3 h-3" /> Terbit</span>
                            @else
                                <span class="badge badge-warning"><x-icon name="clock" class="w-3 h-3" /> Sebagian terbit</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>
@endif

@endsection