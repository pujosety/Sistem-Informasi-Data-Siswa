@extends('components.app-shell')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan')
@php
    $settingsDescription = 'Konfigurasi aplikasi '.config('branding.platform.name');
@endphp
@section('page-description', $settingsDescription)

@section('content')

@if (auth()->user()->hasRole('super_admin') && ($schools ?? collect())->count() > 1)
    <x-card class="mb-5" title="Sekolah Aktif" icon="school"
            description="Pilih sekolah yang sedang dikelola. Data CMS, settings, media, dan branding terisolasi per sekolah.">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-small font-semibold text-[var(--app-text)]">{{ $activeSchool?->name }}</p>
                <p class="text-caption text-[var(--app-text-muted)]">Slug: {{ $activeSchool?->slug }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($schools as $school)
                    <form method="POST" action="{{ route('settings.school.switch', $school) }}">
                        @csrf
                        <button type="submit" class="btn {{ $activeSchool?->id === $school->id ? 'btn-primary' : 'btn-secondary' }}">
                            {{ $school->name }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    </x-card>
@endif

<div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach ($cards as $card)
        @continue(! auth()->user()->can($card['permission']))
        <x-card>
            <a href="{{ route($card['route']) }}" class="group block">
                <span class="grid place-items-center w-11 h-11 rounded-[var(--radius-md)] bg-brand-50 text-brand-700 mb-3
                             group-hover:bg-brand-100 transition-colors">
                    <x-icon :name="$card['icon']" class="w-5 h-5" />
                </span>
                <h2 class="text-h3 font-semibold text-[var(--app-text)] group-hover:text-[var(--app-primary)] transition-colors">
                    {{ $card['title'] }}
                </h2>
                <p class="mt-1 text-small text-[var(--app-text-muted)]">{{ $card['desc'] }}</p>
                @if (filled($card['summary'] ?? null))
                    <p class="mt-3 text-caption font-semibold text-[var(--app-primary)]">{{ $card['summary'] }}</p>
                @endif
            </a>
        </x-card>
    @endforeach
</div>

<x-alert variant="info" class="mt-5" title="Keamanan konfigurasi"
         message="Kunci aplikasi, kredensial database, dan password tidak ditampilkan di sini. Nilai tersebut dikelola lewat konfigurasi server." />
@endsection
