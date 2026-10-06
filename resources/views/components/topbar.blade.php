@props([
    'breadcrumbs' => [],
])

<header class="sticky top-0 z-30 bg-[var(--app-surface)]/85 backdrop-blur-md border-b border-[var(--app-border)]">
    <div class="flex items-center gap-3 h-16 px-4 sm:px-6">

        {{-- Mobile: menu trigger --}}
        <button type="button" @click="$store.app.openMobileNav()"
                class="lg:hidden grid place-items-center w-10 h-10 -ml-2 rounded-[var(--radius-md)] text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)]"
                aria-label="Buka menu navigasi">
            <x-icon name="menu" class="w-5 h-5" />
        </button>

        {{-- Breadcrumbs (desktop) --}}
        @if (count($breadcrumbs))
            <nav aria-label="Breadcrumb" class="hidden sm:block min-w-0">
                <ol class="flex items-center gap-1.5 text-small text-[var(--app-text-muted)]">
                    @foreach ($breadcrumbs as $crumb)
                        <li class="flex items-center gap-1.5 min-w-0">
                            @if (! $loop->first)
                                <x-icon name="chevron-right" class="w-3.5 h-3.5 shrink-0 text-[var(--app-text-subtle)]" />
                            @endif
                            @if ($crumb['url'] && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" class="hover:text-[var(--app-text)] hover:underline transition-colors truncate">
                                    {{ $crumb['label'] }}
                                </a>
                            @else
                                <span class="font-semibold text-[var(--app-text)] truncate" @if ($loop->last) aria-current="page" @endif>
                                    {{ $crumb['label'] }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        <div class="flex-1"></div>

        {{-- Global search + command palette (brief §31). --}}
        <div class="hidden md:flex w-full max-w-sm items-center">
            @php
                // Permission-gated, exactly like the sidebar. A command palette
                // full of links that 403 is worse than no palette: it advertises
                // the whole application to someone who may only use a third of it.
                $commandActions = [];

                foreach (app(\App\Services\NavigationService::class)->forUser(auth()->user())['items'] ?? [] as $item) {
                    // The palette is built from the SAME navigation the sidebar
                    // renders, which is already permission-filtered. Deriving it
                    // here rather than from a second list means a menu item that
                    // appears in the rail cannot be absent from the palette, and
                    // neither can appear for someone who may not reach it.
                    // A group contributes its children, so the palette stays
                    // flat — a two-level list inside a search box is worse than
                    // the flat one the reference component uses.
                    $entries = ! empty($item['children']) ? $item['children'] : [$item];

                    foreach ($entries as $entry) {
                        if (! isset($entry['route'])) {
                            continue;
                        }

                        $commandActions[] = [
                            'label' => $entry['label'],
                            'url'   => route($entry['route']),
                            'icon'  => $entry['icon'] ?? 'corner-down-left',
                            'group' => ! empty($item['children']) ? $item['label'] : null,
                        ];
                    }
                }
            @endphp

            <x-command-palette :actions="$commandActions" />
        </div>

        {{-- Notification centre: a drawer of deep-linked items (brief §32). --}}
        <x-notification-center
            :items="$notificationItems ?? []"
            :unread-count="$unreadNotifications ?? 0" />

        <x-theme-switcher compact />

        {{-- User menu (desktop) --}}
        <div x-data="{ open: false }" class="relative hidden sm:block">
            <button type="button" @click="open = !open" @click.outside="open = false"
                    class="flex items-center gap-2 h-10 pl-1 pr-2 rounded-[var(--radius-md)] hover:bg-[var(--app-surface-muted)] transition-colors"
                    :aria-expanded="open ? 'true' : 'false'" aria-haspopup="true">
                <span class="grid place-items-center w-8 h-8 rounded-full bg-brand-100 text-brand-700 text-caption font-bold">
                    {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 2)) }}
                </span>
                <x-icon name="chevron-down" class="w-4 h-4 text-[var(--app-text-subtle)]" />
            </button>

            <div x-show="open" x-transition.origin.top.right
                 class="absolute right-0 mt-2 w-60 surface-flush shadow-[var(--shadow-overlay)] z-50">
                <div class="px-4 py-3 border-b border-[var(--app-border)]">
                    <p class="text-small font-semibold truncate">{{ auth()->user()?->name }}</p>
                    <p class="text-caption text-[var(--app-text-muted)] truncate">{{ auth()->user()?->email }}</p>
                </div>
                <div class="p-1.5">
                    <a href="{{ route('profile.edit') }}"
                       class="flex items-center gap-2.5 px-3 py-2 rounded-[var(--radius-md)] text-body hover:bg-[var(--app-surface-muted)] transition-colors">
                        <x-icon name="user" class="w-4 h-4 text-[var(--app-text-muted)]" />
                        Profil Saya
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2.5 px-3 py-2 rounded-[var(--radius-md)] text-body text-[var(--app-danger)] hover:bg-[var(--app-danger-soft)] transition-colors">
                            <x-icon name="log-out" class="w-4 h-4" />
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
