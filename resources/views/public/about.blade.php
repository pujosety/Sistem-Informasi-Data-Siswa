@extends('public.layout')

@php $title = 'Tentang sekolah · ' . ($school['name'] ?: config('branding.platform.name')); @endphp
@section('title', $title)
@php $description = 'Profil dan informasi ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $description)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Profil sekolah</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight text-white">
            {{ $school['name'] ?: config('branding.platform.name') }}
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-14 sm:py-20">
    <div class="grid items-center gap-10 lg:grid-cols-[1.05fr_0.95fr]">
        <figure class="group relative overflow-hidden rounded-[1.5rem] border border-[var(--app-border)] bg-[var(--app-surface)] shadow-xl">
            <x-public.responsive-image
                src="{{ asset('images/schools/smpn4metro/landing/profile-community.jpg') }}"
                alt="Keluarga sekolah SMP Negeri 4 Metro berdiskusi bersama"
                class="aspect-[4/3] w-full object-cover transition duration-700 group-hover:scale-[1.03]"
                loading="eager"
                decoding="async"
                width="1672"
                height="941"
                sizes="(min-width: 1024px) 50vw, 100vw"
                fetchpriority="high" />
            <figcaption class="absolute inset-x-4 bottom-4 rounded-xl border border-white/20 bg-black/45 px-4 py-3 text-small text-white backdrop-blur-md">
                <span class="font-semibold">SMP Negeri 4 Metro</span>
                <span class="mt-0.5 block text-white/75">Cerdas, berkarakter, berprestasi.</span>
            </figcaption>
        </figure>
        <div>
            <p class="text-small font-semibold uppercase tracking-[0.16em] text-[var(--app-primary)]">Kenalan lebih dekat</p>
            <h2 class="mt-3 text-h1 font-bold tracking-tight text-balance">Tempat ide tumbuh dan masa depan mulai dibentuk.</h2>
            <p class="mt-5 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                SMP Negeri 4 Metro mengembangkan prestasi akademik dan non-akademik, karakter mulia, serta budaya bersih, sehat, dan peduli lingkungan.
            </p>
            <div class="mt-7 flex flex-wrap gap-2 text-caption font-semibold text-[var(--app-primary)]">
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Berprestasi</span>
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Berkarakter</span>
                <span class="rounded-full bg-[var(--public-soft)] px-3 py-1.5">Peduli lingkungan</span>
            </div>
        </div>
    </div>

    @if (filled($school['headmaster']))
        <p class="text-body text-[var(--app-text-muted)]">
           Kepala Sekolah: <span class="font-semibold text-[var(--app-text)]">{{ $school['headmaster'] }}</span>
        </p>
    @endif

    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            'NPSN' => $school['npsn'],
            'Alamat' => $school['address'],
            'Wilayah' => collect([$school['city'], $school['province']])->filter()->join(', '),
            'Telepon' => $school['phone'],
            'Email' => $school['email'],
            'Situs web' => $school['website'],
        ] as $label => $value)
            @if (filled($value))
                <div class="surface p-5">
                    <p class="text-caption uppercase tracking-[0.14em] text-[var(--app-text-muted)]">{{ $label }}</p>
                    <p class="mt-1.5 text-body break-words">{{ $value }}</p>
                </div>
            @endif
        @endforeach
    </div>

    {{--
        A school that has filled in nothing renders an empty profile. Say so,
        rather than presenting a blank grid of labels.
    --}}
    @if (blank($school['npsn']) && blank($school['address']) && blank($school['city'])
        && blank($school['province']) && blank($school['email']) && blank($school['phone'])
        && blank($school['website']) && blank($school['headmaster']))
        <p class="mt-8 text-body text-[var(--app-text-muted)]">
            Detail profil belum dilengkapi. Administrator sekolah dapat mengisinya
            melalui <span class="font-mono text-[13px]">Pengaturan → Sekolah</span>.
        </p>
    @endif
</section>
@endsection
