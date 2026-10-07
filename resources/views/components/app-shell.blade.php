@props([
    'breadcrumbs' => [],
])

@php
    $runtimeBrandCss = app(\App\Services\BrandService::class)->cssVariables(
        data_get($brand ?? [], 'primary'),
        data_get($brand ?? [], 'accent'),
    );
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" style="{{ $runtimeBrandCss }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Read from config/branding.php rather than written here. It used to be
         #0b3375 while --app-primary was maroon, so an Android status bar showed
         one brand above a page in another; two literals cannot stay in step. --}}
    <meta name="theme-color" content="{{ data_get($brand ?? [], 'primary') ?: config('branding.theme_color') }}">
    <script>
        (() => {
            const key = 'lyfla.theme';
            const stored = localStorage.getItem(key);
            const preference = ['light', 'dark', 'system'].includes(stored) ? stored : 'system';
            const effective = preference === 'system'
                ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : preference;
            document.documentElement.dataset.themePreference = preference;
            document.documentElement.dataset.theme = effective;
            document.documentElement.style.colorScheme = effective;
        })();
    </script>
    <meta name="description" content="Pengelolaan data siswa, dokumen, dan verifikasi pendaftaran.">

    {{-- One naming rule everywhere: "<halaman> · LYFLA". --}}
    @php
        $appBrandName = data_get($brand ?? [], 'shortName') ?: config('branding.platform.name');
        $appBrandFullName = data_get($brand ?? [], 'name') ?: $appBrandName;
    @endphp
    <title>@yield('title', 'Dashboard') · {{ $appBrandName }}</title>

    <link rel="manifest" href="/manifest.webmanifest">

    {{-- Favicons. The multi-resolution .ico covers older browsers; the
         explicit PNGs keep the mark sharp on modern ones. --}}
    <link rel="icon" href="{{ asset('branding/favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('branding/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('branding/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('branding/apple-touch-icon.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ $appBrandName }}">
    <meta name="description" content="{{ $appBrandFullName }} — {{ config('branding.platform.expansion') }}.">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|manrope:600,700,800&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>

<body x-data class="h-full bg-[var(--app-bg)] text-[var(--app-text)] antialiased"
      style="overflow-x:hidden">

<a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[100]
          focus:rounded-[var(--radius-md)] focus:bg-[var(--app-primary)] focus:px-4 focus:py-2 focus:text-white focus:font-semibold">
    Lewati ke konten utama
</a>

{{-- Flash messages are handed to the toast store by app.js --}}
<script type="application/json" id="sida-flashes">@json($__flashes ?? [])</script>

<div class="min-h-full flex">

    {{-- ============ Desktop sidebar ============ --}}
    <aside class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:max-h-screen shrink-0 flex-col bg-[var(--app-sidebar-bg)] transition-[width] duration-200 ease-out"
           :data-collapsed="$store.app.sidebarCollapsed ? 'true' : 'false'"
           :class="$store.app.sidebarCollapsed ? 'w-[72px]' : 'w-64'">
        <x-sidebar :navigation="$navigation" :workspaces="$workspaces ?? []" />
    </aside>

    {{-- ============ Mobile drawer ============ --}}
    <div x-show="$store.app.mobileNavOpen" x-cloak
         class="lg:hidden fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="mobile-nav-title">
        <div x-show="$store.app.mobileNavOpen" x-transition.opacity
             @click="$store.app.closeMobileNav()" class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"></div>
        <aside x-show="$store.app.mobileNavOpen"
               x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
               transition:enter="transition duration-200 ease-out" transition:leave="transition duration-150 ease-in"
               class="absolute inset-y-0 left-0 w-[min(20rem,88vw)] max-w-[88vw] bg-[var(--app-sidebar-bg)] flex min-h-0 flex-col shadow-[var(--shadow-overlay)]">
            <x-sidebar :navigation="$navigation" :workspaces="$workspaces ?? []" mobile />
        </aside>
    </div>

    {{-- ============ Main column ============ --}}
    <div class="flex-1 flex flex-col min-w-0">

        <x-topbar :breadcrumbs="$breadcrumbs" />

        <main id="main-content" class="flex-1 w-full max-w-[1440px] mx-auto px-4 sm:px-6 py-5 sm:py-7
                     pb-[max(5.5rem,env(safe-area-inset-bottom))] lg:pb-8">
            {{-- Page header driven by sections, so views stay declarative.
                 Each section's presence is captured BEFORE yieldContent() is
                 called: yielding consumes the buffer, so a later hasSection()
                 on the same name would return false and unbalance the
                 @if/@endif pairing. --}}
            @php
                $hasTitle = View::hasSection('page-title');
                $hasDescription = View::hasSection('page-description');
                $hasActions = View::hasSection('page-actions');

                $pageTitle = $hasTitle ? View::yieldContent('page-title') : null;
                $pageDescription = $hasDescription ? View::yieldContent('page-description') : null;
                $pageActions = $hasActions ? View::yieldContent('page-actions') : null;
            @endphp

            @if ($hasTitle)
                <x-page-header :title="$pageTitle" :description="$pageDescription">
                    @if ($hasActions)
                        <x-slot:actions>{!! $pageActions !!}</x-slot:actions>
                    @endif
                </x-page-header>
            @endif

            @yield('content')
        </main>
    </div>
</div>

{{-- ============ Mobile bottom dock + more sheet ============ --}}
{{-- The sheet lists what the dock could not fit; the dock is capped at four
     destinations plus this Menu button so the bar never exceeds five slots. --}}
<x-mobile-dock :items="$dockItems" :more="$moreItems ?? []" />
<x-more-menu :items="$moreItems ?? []" />

{{-- ============ Toasts ============ --}}
<div class="fixed z-[60] bottom-20 sm:bottom-6 right-3 sm:right-6 left-3 sm:left-auto sm:w-96 flex flex-col gap-2 pointer-events-none"
     x-data aria-live="polite" aria-atomic="false">
    <template x-for="t in $store.toast.items" :key="t.id">
        <div x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 scale-95"
             transition:enter="transition duration-200 ease-out"
             class="pointer-events-auto surface-flush shadow-[var(--shadow-overlay)] p-3.5 flex items-start gap-2.5"
             :class="{
                'border-l-4 border-l-[var(--app-success)]': t.type === 'success',
                'border-l-4 border-l-[var(--app-danger)]':  t.type === 'error',
                'border-l-4 border-l-[var(--app-warning)]': t.type === 'warning',
                'border-l-4 border-l-[var(--app-info)]':    t.type === 'info',
             }"
             role="status">
            <span class="shrink-0 mt-0.5"
                  :class="{
                    'text-[var(--app-success)]': t.type === 'success',
                    'text-[var(--app-danger)]':  t.type === 'error',
                    'text-[var(--app-warning)]': t.type === 'warning',
                    'text-[var(--app-info)]':    t.type === 'info',
                  }">
                <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                    <template x-if="t.type === 'success'"><path d="M20 6 9 17l-5-5"/></template>
                    <template x-if="t.type === 'error'"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></template>
                    <template x-if="t.type === 'warning'"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4"/><path d="M12 17h.01"/></template>
                    <template x-if="t.type === 'info'"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></template>
                </svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-small font-semibold text-[var(--app-text)]" x-text="t.title"></p>
                <p class="text-caption text-[var(--app-text-muted)] mt-0.5" x-show="t.message" x-text="t.message"></p>
            </div>
            <button type="button" @click="$store.toast.dismiss(t.id)"
                    class="shrink-0 grid place-items-center w-6 h-6 rounded text-[var(--app-text-subtle)] hover:bg-[var(--app-surface-muted)]"
                    aria-label="Tutup notifikasi">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>
    </template>
</div>

{{-- Collapsed-rail label hiding is pure CSS, driven by the attribute Alpine
     toggles on the same element that controls the width. No polling. --}}
<style>
    [data-collapsed='true'] .nav-label { display: none; }
</style>

@stack('scripts')
</body>
</html>
