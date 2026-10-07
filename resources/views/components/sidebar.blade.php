@props([
    'navigation' => [],
    'workspaces' => [],
    'mobile' => false,
])

@php
    // The workspace is resolved from ROLE + ASSIGNMENT, never hardcoded here.
    // A Wali Kelas is a kesiswaan with an assignment, so the label reflects
    // their tier and the switcher offers "Kelas Saya" when it exists.
    $user = auth()->user();
    $activeWorkspace = collect($workspaces ?? [])
        ->first(fn ($w) => request()->routeIs($w['route']));

    $workspaceLabel = $activeWorkspace['label']
        ?? (($workspaces ?? [])[0]['label'] ?? 'Siswa');
    $workspaceIcon = $activeWorkspace['icon'] ?? 'graduation-cap';
@endphp

<div class="relative flex flex-col h-full min-h-0">

    {{-- Brand + workspace ---------------------------------------------------- --}}
    {{-- Official LYFLA mark, not an icon-font stand-in: the mark combines a
         graduation cap with a flowing S-ribbon and has no Lucide equivalent.
         The wordmark beside it is real text, so it collapses with the rail and
         the emblem never moves. --}}
    <div class="flex items-center gap-2.5 h-16 px-4 pr-12 shrink-0 border-b border-white/8">
        @if ($mobile)
            <h2 id="mobile-nav-title" class="sr-only">Menu navigasi</h2>
        @endif
        {{-- The emblem carries its own deep navy, which measures 1.8:1 against
             this rail and disappears. A quiet light plate gives it the
             contrast a graphical object needs without recolouring the official
             artwork or applying a filter to it. --}}
        <span class="nav-brand-icon grid place-items-center w-9 h-9 shrink-0
                     rounded-[var(--radius-md)] bg-white">
            <x-brand.logo variant="icon" height="h-8" :alt="data_get($brand ?? [], 'shortName') ?: config('branding.platform.name')" />
        </span>

        <div class="min-w-0 nav-label">
            <p class="text-body font-bold text-white leading-tight truncate">{{ data_get($brand ?? [], 'shortName') ?: config('branding.platform.name') }}</p>
            <p class="text-[11px] text-[var(--app-sidebar-text)] leading-tight truncate">{{ $workspaceLabel }}</p>
        </div>

        <button type="button" @click="$store.app.toggle()"
                class="ml-auto hidden lg:grid place-items-center w-8 h-8 rounded-[var(--radius-md)]
                       text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/10 transition-colors"
                aria-label="Ciutkan atau perluas navigasi">
            <x-icon name="panel-left" class="w-[18px] h-[18px]" />
        </button>
        @if ($mobile)
            <button type="button" @click="$store.app.closeMobileNav()"
                    class="lg:hidden absolute top-3 right-3 grid place-items-center w-10 h-10 rounded-[var(--radius-md)]
                           text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/10 transition-colors"
                    aria-label="Tutup menu navigasi">
                <x-icon name="x" class="w-5 h-5" />
            </button>
        @endif
    </div>

    {{-- Workspace switcher ----------------------------------------------------- --}}
    @php $switchable = array_values(array_filter($workspaces ?? [], fn ($w) => count($workspaces ?? []) > 1)); @endphp
    @if ($switchable)
        <div class="px-3 pt-3 shrink-0 nav-label" x-data="{ open: false }">
            <button type="button" @click="open = !open" :aria-expanded="open"
                    class="w-full flex items-center gap-2 px-2.5 py-2 rounded-[var(--radius-md)]
                           bg-white/5 hover:bg-white/10 transition-colors text-left">
                <span class="grid place-items-center w-7 h-7 shrink-0 rounded-[var(--radius-sm)] bg-white/10">
                    <x-icon :name="$workspaceIcon" class="w-4 h-4 text-white" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-caption text-[var(--app-sidebar-text)] leading-none">Ruang Kerja</span>
                    <span class="block text-small font-semibold text-white leading-tight truncate mt-0.5">{{ $workspaceLabel }}</span>
                </span>
                <x-icon name="chevrons-up-down" class="w-3.5 h-3.5 text-[var(--app-sidebar-text)] shrink-0" />
            </button>

            <div x-show="open" x-cloak @click.outside="open = false"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-1 rounded-[var(--radius-md)] bg-ink-900 ring-1 ring-white/10 overflow-hidden shadow-lg">
                @foreach ($switchable as $w)
                    @php $isCurrent = ($activeWorkspace['key'] ?? null) === $w['key']; @endphp
                    <a href="{{ route($w['route']) }}"
                       @class([
                           'flex items-center gap-2.5 px-3 py-2.5 text-small transition-colors',
                           'bg-white/10 text-white font-semibold' => $isCurrent,
                           'text-[var(--app-sidebar-text)] hover:bg-white/5 hover:text-white' => ! $isCurrent,
                       ])>
                        <x-icon :name="$w['icon']" class="w-4 h-4 shrink-0" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate">{{ $w['label'] }}</span>
                            <span class="block text-[11px] opacity-70">{{ \Illuminate\Support\Str::headline($w['tier']) }}</span>
                        </span>
                        @if ($isCurrent)
                            <x-icon name="check" class="w-4 h-4 shrink-0" />
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Navigation ------------------------------------------------------------- --}}
    <nav class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden p-3 space-y-0.5 scrollbar-thin"
         aria-label="Navigasi utama">
        <x-nav-items :items="$navigation" />
    </nav>

    {{-- Account ---------------------------------------------------------------- --}}
    <div class="p-3 shrink-0 border-t border-white/8">
        <div class="flex items-center gap-2.5 px-2 py-2 rounded-[var(--radius-md)] bg-white/5">
            <span class="grid place-items-center w-8 h-8 shrink-0 rounded-full bg-white/15 text-white text-caption font-bold">
                {{ strtoupper(mb_substr($user?->name ?? '?', 0, 2)) }}
            </span>
            <div class="min-w-0 flex-1 nav-label">
                <p class="text-small font-semibold text-white truncate">{{ $user?->name }}</p>
                <p class="text-[11px] text-[var(--app-sidebar-text)] truncate">{{ $user?->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="nav-label shrink-0">
                @csrf
                <button type="submit"
                        class="grid place-items-center w-8 h-8 rounded-[var(--radius-md)]
                               text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/10 transition-colors"
                        aria-label="Keluar" title="Keluar">
                    <x-icon name="log-out" class="w-[18px] h-[18px]" />
                </button>
            </form>
        </div>
    </div>
</div>
