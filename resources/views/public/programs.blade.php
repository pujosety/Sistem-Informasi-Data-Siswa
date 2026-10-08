@extends('public.layout')

@php $pageTitle = 'Program Studi · ' . ($school['name'] ?: config('branding.platform.name')); @endphp
@section('title', $pageTitle)
@php $pageDescription = 'Program dan mata pelajaran di ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $pageDescription)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Program</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
            Program dan mata pelajaran
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-14 sm:py-20">
    <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
        <div>
            <p class="text-small font-semibold uppercase tracking-[0.16em] text-[var(--app-primary)]">Temukan bidang yang bikin kamu penasaran.</p>
            <h2 class="mt-3 text-h1 font-bold tracking-tight text-balance">Belajar bukan cuma soal teori.</h2>
            <p class="mt-5 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                Dari sains, bahasa, teknologi, hingga kegiatan kreatif, setiap program dirancang untuk membantu siswa menemukan kekuatan dan arah belajarnya.
            </p>
            <div class="mt-7 flex flex-wrap gap-2 text-caption font-semibold text-[var(--app-primary)]">
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Kurikulum aktif</span>
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Kegiatan beragam</span>
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Pendampingan guru</span>
            </div>
        </div>
        <figure class="group relative overflow-hidden rounded-[1.5rem] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-xl">
            <img src="{{ asset('images/schools/smpn4metro/landing/facility-learning.jpg') }}"
                 alt="Siswa SMP Negeri 4 Metro berdiskusi dalam pembelajaran"
                 class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                 loading="eager" decoding="async">
            <figcaption class="absolute inset-x-4 bottom-4 rounded-xl border border-white/20 bg-black/45 px-4 py-3 text-small text-white backdrop-blur-md">
                Pembelajaran aktif di dalam dan di luar kelas
            </figcaption>
        </figure>
    </div>

    <div class="mt-14 grid gap-5 sm:grid-cols-3">
        @foreach ([
            ['Program Seni', 'program-seni.jpg', 'Ruang untuk berekspresi, berkolaborasi, dan berani tampil.'],
            ['Program Olahraga', 'program-olahraga.jpg', 'Membangun kebugaran, sportivitas, dan kerja sama.'],
            ['Pramuka', 'program-pramuka.jpg', 'Melatih kepemimpinan, kepedulian, dan kemandirian.'],
        ] as [$label, $image, $description])
            <article class="group overflow-hidden rounded-[1.25rem] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                <img src="{{ asset('images/schools/smpn4metro/landing/'.$image) }}" alt="{{ $label }} SMP Negeri 4 Metro" class="aspect-[16/10] w-full object-cover transition duration-700 group-hover:scale-[1.04]" loading="lazy">
                <div class="p-5">
                    <p class="text-small font-bold text-[var(--app-text)]">{{ $label }}</p>
                    <p class="mt-2 text-small leading-relaxed text-[var(--app-text-muted)]">{{ $description }}</p>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-12">
        @forelse ($departments as $department)
            <article id="program-{{ $department['id'] }}" class="scroll-mt-24 mb-8 last:mb-0">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="text-h2 font-semibold">{{ $department['name'] }}</h2>
                @if ($department['classCount'] > 0)
                    <p class="text-caption text-[var(--app-text-muted)]">
                        {{ $department['classCount'] }} kelas aktif
                    </p>
                @endif
            </div>

            {{--
                Subjects, not classes. Naming the classes here would make this a
                roster-shaped page one link away, which §10 does not permit:
                class membership is derived from enrolment and identifies
                students.
            --}}
            <ul class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($department['subjects'] as $subject)
                    <li class="surface px-4 py-2.5 text-body">
                        {{ $subject->name }}
                    </li>
                @empty
                    <li class="text-body text-[var(--app-text-muted)] sm:col-span-2 lg:col-span-3">
                        Belum ada mata pelajaran yang ditetapkan.
                    </li>
                @endforelse
            </ul>
        </article>
    @empty
        <div class="surface p-8 text-center">
            <p class="text-body font-semibold">Program belum ditetapkan</p>
            <p class="mt-1.5 text-body text-[var(--app-text-muted)]">
                Administrator sekolah dapat menambahkannya melalui
                <span class="font-mono text-[13px]">Akademik → Mata Pelajaran</span>.
            </p>
        </div>
    @endforelse
    </div>
</section>
@endsection
