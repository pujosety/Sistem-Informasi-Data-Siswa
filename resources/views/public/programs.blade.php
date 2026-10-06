@extends('public.layout')

@php $pageTitle = 'Program Studi · ' . ($school['name'] ?: config('branding.platform.name')); @endphp
@section('title', $pageTitle)
@php $pageDescription = 'Program dan mata pelajaran di ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $pageDescription)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Program</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            Program dan mata pelajaran
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
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
</section>
@endsection
