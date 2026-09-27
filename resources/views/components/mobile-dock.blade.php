@props(['items' => []])

{{-- Bottom dock for phones. Shows at most 5 destinations; the rest live in the
     drawer, so this never becomes an unusable icon strip. --}}

<nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 bg-[var(--app-surface)]/95 backdrop-blur-md border-t border-[var(--app-border)]"
     style="padding-bottom:env(safe-area-inset-bottom)"
     aria-label="Navigasi cepat">
    <div class="grid" :style="`grid-template-columns: repeat(${Math.min(count($items), 5)}, minmax(0, 1fr))`">
        @foreach (array_slice($items, 0, 5) as $item)
            @php $isActive = request()->routeIs($item['active'] ?? $item['route']); @endphp
            <a href="{{ route($item['route']) }}"
               class="flex flex-col items-center justify-center gap-1 min-h-[56px] px-1 py-2 transition-colors relative
                      {{ $isActive ? 'text-brand-700' : 'text-[var(--app-text-muted)]' }}"
               @if ($isActive) aria-current="page" @endif>
                @if ($isActive)
                    <span class="absolute top-0 left-1/2 -translate-x-1/2 h-0.5 w-8 rounded-full bg-brand-600"></span>
                @endif
                <x-icon :name="$item['icon']" class="w-[22px] h-[22px]" />
                <span class="text-[10px] font-semibold leading-none text-center truncate max-w-full">{{ $item['short'] ?? $item['label'] }}</span>
                @if (($item['badge'] ?? 0) > 0)
                    <span class="absolute top-1.5 right-[22%] min-w-[15px] h-[15px] px-1 grid place-items-center rounded-full bg-[var(--app-danger)] text-white text-[9px] font-bold">
                        {{ $item['badge'] > 9 ? '9+' : $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
