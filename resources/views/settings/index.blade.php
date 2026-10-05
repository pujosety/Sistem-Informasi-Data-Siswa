@extends('components.app-shell')

@section('title', 'Pengaturan')
@section('page-title', 'Pengaturan')
@section('page-description', 'Konfigurasi aplikasi {{ config('branding.platform.name') }}')

@section('content')

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
