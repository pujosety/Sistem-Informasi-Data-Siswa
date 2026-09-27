@props([
    'items' => [],
    'activePattern' => null,
])

{{-- Renders the role-scoped navigation. Desktop sidebar and mobile drawer/dock
     both consume this so a menu change only has to be made once. --}}

@foreach ($items as $item)
    @php
        $isActive = $activePattern && request()->routeIs($item['active'] ?? $item['route']);
        $badge = $item['badge'] ?? null;
    @endphp

    @if ($item['children'] ?? false)
        <div x-data="{ open: @js($isActive) }" class="space-y-0.5">
            <button type="button" @click="open = !open"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius-md)] text-body font-medium transition-colors"
                    :class="open || {{ $isActive ? 'true' : 'false' }} ? 'text-white' : 'text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/5'"
                    :aria-expanded="open ? 'true' : 'false'">
                <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0" />
                <span class="flex-1 text-left nav-label">{{ $item['label'] }}</span>
                <x-icon name="chevron-down" class="w-4 h-4 shrink-0 transition-transform nav-label" ::class="open && 'rotate-180'" />
            </button>

            <div x-show="open" x-collapse class="ml-4 pl-3 border-l border-white/10 space-y-0.5 mt-0.5">
                @foreach ($item['children'] as $child)
                    <a href="{{ route($child['route']) }}"
                       class="block px-3 py-2 rounded-[var(--radius-md)] text-small nav-label transition-colors
                              {{ request()->routeIs($child['active'] ?? $child['route']) ? 'text-white bg-white/10 font-semibold' : 'text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/5' }}">
                        {{ $child['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    @else
        <a href="{{ route($item['route']) }}"
           class="flex items-center gap-3 px-3 py-2.5 rounded-[var(--radius-md)] text-body font-medium transition-colors
                  {{ $isActive ? 'text-white bg-white/10' : 'text-[var(--app-sidebar-text)] hover:text-white hover:bg-white/5' }}"
           @if ($isActive) aria-current="page" @endif>
            <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0" />
            <span class="flex-1 nav-label">{{ $item['label'] }}</span>
            @if ($badge)
                <span class="nav-label shrink-0 min-w-[20px] h-5 px-1.5 grid place-items-center rounded-full bg-[var(--app-danger)] text-white text-[11px] font-bold">
                    {{ $badge > 99 ? '99+' : $badge }}
                </span>
            @endif
        </a>
    @endif
@endforeach
