@extends('public.layout')

@php $title = 'Tentang sekolah · ' . ($school['name'] ?: config('branding.platform.name')); @endphp
@section('title', $title)
@php $description = 'Profil dan informasi ' . ($school['name'] ?: 'sekolah') . '.'; @endphp
@section('description', $description)

@section('body')
<section class="bg-[var(--app-sidebar-bg)] text-white">
    <div class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
        <p class="text-caption uppercase tracking-[0.18em] text-white/50">Profil sekolah</p>
        <h1 class="mt-2 font-[var(--font-display)] text-3xl sm:text-4xl font-extrabold tracking-tight">
            {{ $school['name'] ?: config('branding.platform.name') }}
        </h1>
    </div>
</section>

<section class="mx-auto max-w-6xl px-5 sm:px-8 py-12">
    <div class="grid items-center gap-8 lg:grid-cols-[0.9fr_1.1fr]">
        <x-image-placeholder type="profile" aspect="4/3" alt="Siswa SMP 1 LYFLA belajar bersama" />
        <div>
            <p class="text-small font-semibold text-[var(--app-primary)]">Kenalan lebih dekat</p>
            <h2 class="mt-2 text-h1 font-bold tracking-tight">Tempat ide tumbuh dan masa depan mulai dibentuk.</h2>
            <p class="mt-4 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">
                Profil ini merangkum informasi resmi yang dipublikasikan sekolah. Detail akan mengikuti pengaturan yang dikelola administrator.
            </p>
        </div>
    </div>

    @if (filled($school['headmaster']))
        <p class="text-body text-[var(--app-text-muted)]">
           Kepala Sekolah: <span class="font-semibold text-[var(--app-text)]">{{ $school['headmaster'] }}</span>
        </p>
    @endif

    <div class="mt-6 grid gap-6 sm:grid-cols-2">
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
