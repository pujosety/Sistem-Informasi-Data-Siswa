@extends('components.app-shell')

@section('title', 'LMS · '.$course->title)
@section('page-title', $course->title)
@section('page-description', $course->subject?->name.' · '.$course->classroom?->name)

@section('content')
<div class="space-y-4">
    @if (session('success'))
        <x-alert variant="success" class="mb-4" :message="session('success')" />
    @endif

    <x-card>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-caption text-[var(--app-text-muted)]">{{ $course->code }}</p>
                <h2 class="mt-1 text-h2 font-semibold text-[var(--app-text)]">{{ $course->title }}</h2>
                <p class="mt-2 text-body text-[var(--app-text-muted)]">{{ $course->description ?: 'Belum ada deskripsi.' }}</p>
            </div>
            <div class="flex items-center gap-2">
                @can('lms.gradebook.view')
                    <a href="{{ route('lms.teacher.gradebook.index', $course) }}" class="btn btn-secondary btn-sm">Gradebook</a>
                @endcan
                @can('lms.enrollment.manage')
                    <a href="{{ route('lms.teacher.enrollments.index', $course) }}" class="btn btn-secondary btn-sm">Peserta</a>
                @endcan
                <span class="badge {{ $course->status === 'published' ? 'badge-success' : 'badge-neutral' }}">
                {{ $course->status === 'published' ? 'Terbit' : 'Draft' }}
            </span>
            </div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-3">
            <div><p class="text-caption text-[var(--app-text-muted)]">Kelas</p><p class="font-semibold">{{ $course->classroom?->name }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Semester</p><p class="font-semibold">{{ $course->semester?->displayLabel() }}</p></div>
            <div><p class="text-caption text-[var(--app-text-muted)]">Materi</p><p class="font-semibold">{{ $course->lessons->count() }} lesson</p></div>
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Pengumuman course</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Informasi penting yang akan diterima peserta course.</p>
            </div>
            @can('lms.lesson.manage')
                <a href="{{ route('lms.teacher.announcements.create', $course) }}" class="btn btn-primary btn-sm">Tambah pengumuman</a>
            @endcan
        </div>
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->announcements as $announcement)
                <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $announcement->title }}</p>
                        <p class="line-clamp-2 text-small text-[var(--app-text-muted)]">{{ $announcement->body }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="badge {{ $announcement->status === 'published' ? 'badge-success' : 'badge-neutral' }}">{{ $announcement->status === 'published' ? 'Terbit' : 'Draft' }}</span>
                        @if ($announcement->status !== 'published')
                            @can('lms.lesson.manage')
                                <form method="POST" action="{{ route('lms.teacher.announcements.publish', [$course, $announcement]) }}">@csrf<button class="btn btn-primary btn-sm">Terbitkan</button></form>
                            @endcan
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada pengumuman.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Bab pembelajaran</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Kelompokkan materi agar alur belajar siswa lebih jelas.</p>
            </div>
            @can('lms.lesson.manage')
                <a href="{{ route('lms.teacher.modules.create', $course) }}" class="btn btn-primary btn-sm">Tambah bab</a>
            @endcan
        </div>
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->modules as $module)
                <div class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $module->position }}. {{ $module->title }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $module->description ?: 'Belum ada deskripsi.' }} · {{ $module->lessons->count() }} materi</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="badge {{ $module->status === 'published' ? 'badge-success' : 'badge-neutral' }}">{{ $module->status === 'published' ? 'Terbit' : 'Draft' }}</span>
                        @can('lms.lesson.manage')
                            <a href="{{ route('lms.teacher.modules.edit', [$course, $module]) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @if ($module->status !== 'published')
                                <form method="POST" action="{{ route('lms.teacher.modules.publish', [$course, $module]) }}">@csrf<button class="btn btn-primary btn-sm">Terbitkan</button></form>
                            @endif
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada bab. Materi masih dapat ditambahkan tanpa bab.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Materi pembelajaran</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Lesson akan muncul di portal siswa setelah diterbitkan.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-neutral">{{ $course->lessons->count() }} materi</span>
                @can('lms.lesson.manage')
                    <a href="{{ route('lms.teacher.lessons.create', $course) }}" class="btn btn-primary btn-sm">Tambah materi</a>
                @endcan
            </div>
        </div>
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->lessons as $lesson)
                <div class="flex items-center justify-between gap-3 py-3">
                    <div>
                        <p class="font-medium">{{ $lesson->position }}. {{ $lesson->title }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $lesson->status === 'published' ? 'Terbit' : 'Draft' }}</p>
                    </div>
                    @can('lms.lesson.manage')
                        <div class="flex items-center gap-2">
                            <a href="{{ route('lms.teacher.lessons.edit', [$course, $lesson]) }}" class="btn btn-secondary btn-sm">Edit</a>
                            @if ($lesson->status !== 'published')
                                <form method="POST" action="{{ route('lms.teacher.lessons.publish', [$course, $lesson]) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm">Terbitkan</button>
                                </form>
                            @endif
                        </div>
                    @endcan
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada materi. Tambahkan materi pertama untuk course ini.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Tugas</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Kelola tugas dan nilai pengumpulan siswa.</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="badge badge-neutral">{{ $course->assignments->count() }} tugas</span>
                @can('lms.assignment.manage')
                    <a href="{{ route('lms.teacher.assignments.create', $course) }}" class="btn btn-primary btn-sm">Tambah tugas</a>
                @endcan
            </div>
        </div>
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->assignments as $assignment)
                <div class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-medium">{{ $assignment->title }}</p>
                        <p class="text-caption text-[var(--app-text-muted)]">{{ $assignment->status === 'published' ? 'Terbit' : 'Draft' }} · {{ $assignment->due_at?->format('d M Y H:i') ?? 'Tanpa deadline' }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($assignment->status !== 'published')
                            @can('lms.assignment.manage')
                                <form method="POST" action="{{ route('lms.teacher.assignments.publish', [$course, $assignment]) }}">@csrf<button class="btn btn-primary btn-sm">Terbitkan</button></form>
                            @endcan
                        @endif
                        @can('lms.assignment.grade')
                            <a href="{{ route('lms.teacher.assignments.submissions', [$course, $assignment]) }}" class="btn btn-secondary btn-sm">Pengumpulan</a>
                        @endcan
                    </div>
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada tugas. Tambahkan tugas pertama untuk course ini.</p>
            @endforelse
        </div>
    </x-card>

    <x-card>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-h3 font-semibold">Kuis</h2>
                <p class="text-caption text-[var(--app-text-muted)]">Buat pertanyaan, terbitkan kuis, dan siapkan evaluasi otomatis.</p>
            </div>
            <span class="badge badge-neutral">{{ $course->quizzes->count() }} kuis</span>
        </div>
        @can('lms.assignment.manage')
            <form method="POST" action="{{ route('lms.teacher.quizzes.store', $course) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                <input name="title" class="input" placeholder="Judul kuis" required>
                <input name="attempt_limit" type="number" min="1" max="10" value="1" class="input" required>
                <textarea name="description" class="input md:col-span-2" rows="2" placeholder="Deskripsi singkat"></textarea>
                <input name="passing_score" type="number" min="0" max="100" step="0.01" value="70" class="input" required>
                <button class="btn btn-primary md:col-span-2 md:justify-self-start">Buat kuis draft</button>
            </form>
        @endcan
        <div class="mt-4 divide-y divide-[var(--app-border)]">
            @forelse ($course->quizzes as $quiz)
                <div class="py-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-medium">{{ $quiz->title }}</p>
                            <p class="text-caption text-[var(--app-text-muted)]">{{ $quiz->status === 'published' ? 'Terbit' : 'Draft' }} · {{ $quiz->questions->count() }} pertanyaan · {{ $quiz->attempt_limit }} percobaan</p>
                        </div>
                        @if ($quiz->status !== 'published')
                            @can('lms.assignment.manage')
                                <form method="POST" action="{{ route('lms.teacher.quizzes.publish', [$course, $quiz]) }}">@csrf<button class="btn btn-primary btn-sm">Terbitkan</button></form>
                            @endcan
                        @endif
                    </div>
                    @can('lms.assignment.manage')
                        <form method="POST" action="{{ route('lms.teacher.quizzes.questions.store', [$course, $quiz]) }}" class="mt-3 grid gap-2 md:grid-cols-4">
                            @csrf
                            <input name="prompt" class="input md:col-span-2" placeholder="Pertanyaan" required>
                            <select name="type" class="input"><option value="multiple_choice">Pilihan ganda</option><option value="true_false">Benar/Salah</option><option value="short_answer">Jawaban singkat</option></select>
                            <input name="correct_answer" class="input" placeholder="Jawaban benar" required>
                            <input name="options[]" class="input md:col-span-2" placeholder="Opsi (isi satu dulu; opsi tambahan lewat API/form lanjutan)">
                            <input name="points" type="number" min="0.01" step="0.01" value="1" class="input">
                            <button class="btn btn-secondary">Tambah pertanyaan</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="py-5 text-body text-[var(--app-text-muted)]">Belum ada kuis untuk course ini.</p>
            @endforelse
        </div>
    </x-card>
</div>
@endsection
