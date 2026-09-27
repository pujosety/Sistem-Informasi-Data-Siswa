@props(['items' => [], 'more' => []])

{{-- Bottom dock for phones.

     Hard cap: FOUR destinations plus the Menu button. A five-item dock plus a
     Menu button is six slots, which is already too many at 360px, and it would
     collide with a sticky "Simpan" action stacked above the bar. --}}

@php
    $dockItems = array_slice($items, 0, 4);
    $hasMore   = count($more) > 0;
    $slots     = count($dockItems) + ($hasMore ? 1 : 0);
@endphp

<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-[var(--app-surface)]/95 backdrop-blur-md border-t border-[var(--app-border)]"
     style="padding-bottom:env(safe-area-inset-bottom)"
     aria-label="Navigasi cepat">
    <div class="grid" style="grid-template-columns: repeat({{ max($slots, 1) }}, minmax(0, 1fr))">

        @foreach ($dockItems as $item)
            @php $isActive = request()->routeIs($item['active'] ?? $item['route']); @endphp
            <a href="{{ route($item['route']) }}"
               class="relative flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2
                      transition-colors {{ $isActive ? 'text-brand-700' : 'text-[var(--app-text-muted)]' }}"
               @if ($isActive) aria-current="page" @endif>
                {{-- Active state uses a bar AND colour, never colour alone --}}
                @if ($isActive)
                    <span class="absolute inset-x-0 top-0 h-0.5 bg-brand-600" aria-hidden="true"></span>
                @endif
                <x-icon :name="$item['icon']" class="w-[22px] h-[22px] shrink-0" />
                <span class="text-[11px] font-semibold leading-none text-center truncate max-w-full">
                    {{ $item['short'] ?? $item['label'] }}
                </span>
                @if (($item['badge'] ?? 0) > 0)
                    <span class="absolute top-1.5 right-[22%] min-w-[16px] h-4 px-1 grid place-items-center
                                 rounded-full bg-[var(--app-danger)] text-white text-[10px] font-bold"
                          aria-label="{{ $item['badge'] }} belum ditangani">
                        {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach

        @if ($hasMore)
            <button type="button" @click="$store.app.openMore()"
                    class="relative flex min-h-[56px] flex-col items-center justify-center gap-1 px-1 py-2
                           text-[var(--app-text-muted)] transition-colors"
                    aria-label="Buka menu lainnya">
                <x-icon name="menu" class="w-[22px] h-[22px] shrink-0" />
                <span class="text-[11px] font-semibold leading-none">Menu</span>
            </button>
        @endif
    </div>
</nav>
