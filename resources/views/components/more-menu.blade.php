@props(['items' => []])

{{-- Secondary destinations for phones. A 9-icon bottom strip is unusable at
     360px, so anything past the dock lives in this bottom sheet. The list is
     built from the same permission-filtered sidebar, so it can never offer a
     link the desktop menu does not. --}}

    <div x-show="$store.app.moreOpen" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-labelledby="more-menu-title">
    <div x-show="$store.app.moreOpen" x-transition.opacity
         @click="$store.app.closeMore()"
         class="absolute inset-0 bg-ink-900/60 backdrop-blur-sm"></div>

    <div x-show="$store.app.moreOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
         class="absolute inset-x-0 bottom-0 max-h-[85vh] overflow-y-auto
                rounded-t-2xl bg-[var(--app-surface)] shadow-2xl"
         style="padding-bottom:env(safe-area-inset-bottom)">

        {{-- Grab handle --}}
        <div class="flex justify-center pt-2.5 pb-1">
            <span class="h-1 w-10 rounded-full bg-[var(--app-border)]"></span>
        </div>

        <header class="flex items-center justify-between px-4 py-2.5 border-b border-[var(--app-border)]">
            <h2 id="more-menu-title" class="text-body font-semibold">Menu Lainnya</h2>
            <button type="button" @click="$store.app.closeMore()"
                    class="grid place-items-center w-9 h-9 rounded-[var(--radius-md)] text-[var(--app-text-muted)] hover:bg-[var(--app-surface-alt)]"
                    aria-label="Tutup menu">
                <x-icon name="x" class="w-5 h-5" />
            </button>
        </header>

        @php
            $grouped = collect($items)->groupBy(fn ($i) => $i['group'] ?? null);
        @endphp

        <nav class="p-2 pb-4">
            @foreach ($grouped as $group => $entries)
                @if ($group)
                    <p class="px-3 pt-4 pb-1.5 text-caption font-semibold uppercase tracking-wide text-[var(--app-text-muted)]">
                        {{ $group }}
                    </p>
                @endif

                <ul class="space-y-0.5">
                    @foreach ($entries as $item)
                        @php $isActive = request()->routeIs($item['active'] ?? $item['route']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @class([
                                   'flex items-center gap-3 rounded-[var(--radius-md)] px-3 py-3 min-h-[48px] transition-colors',
                                   'bg-[var(--app-primary-soft)] text-[var(--app-primary)] font-semibold' => $isActive,
                                   'text-[var(--app-text-secondary)] hover:bg-[var(--app-surface-alt)]' => ! $isActive,
                               ])
                               @if ($isActive) aria-current="page" @endif>
                                <x-icon :name="$item['icon'] ?? 'circle'" class="w-5 h-5 shrink-0" />
                                <span class="min-w-0 flex-1 truncate text-small">{{ $item['label'] }}</span>
                                @if (($item['badge'] ?? 0) > 0)
                                    <span class="shrink-0 min-w-[20px] h-5 px-1.5 grid place-items-center rounded-full
                                                 bg-[var(--app-danger)] text-white text-caption font-bold">
                                        {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                                    </span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>
    </div>
</div>
