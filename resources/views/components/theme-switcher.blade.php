@props(['compact' => false, 'inverse' => false])

<div x-data="themeSwitcher()" class="relative" @keydown.escape.window="open = false">
    <button type="button"
            @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="menu"
            aria-label="Pilihan tampilan"
            title="Pilihan tampilan"
            class="inline-flex min-h-10 items-center gap-2 rounded-[var(--radius-md)] px-2.5 text-small font-semibold transition-colors
                   {{ $inverse ? 'text-white/80 hover:bg-white/10 hover:text-white focus-visible:outline-white' : 'text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)] hover:text-[var(--app-text)] focus-visible:outline-[var(--app-primary)]' }}
                   focus-visible:outline-2 focus-visible:outline-offset-2">
        <x-icon name="sun-moon" class="size-4" />
        @unless ($compact)
            <span class="hidden xl:inline" x-text="label"></span>
        @endunless
        <x-icon name="chevron-down" class="size-3.5" />
    </button>

    <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
         role="menu" aria-label="Pilihan tampilan"
         class="absolute right-0 z-50 mt-2 w-44 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] p-1.5 shadow-[var(--shadow-overlay)]">
        @foreach ([['light', 'Terang'], ['dark', 'Gelap'], ['system', 'Sistem']] as [$value, $optionLabel])
            <button type="button" role="menuitemradio"
                    @click="choose('{{ $value }}')"
                    :aria-checked="preference === '{{ $value }}' ? 'true' : 'false'"
                    class="flex min-h-10 w-full items-center gap-2 rounded-[var(--radius-sm)] px-3 text-left text-small transition-colors
                           text-[var(--app-text)] hover:bg-[var(--app-surface-muted)]"
                    :class="preference === '{{ $value }}' ? 'bg-[var(--app-primary-soft)] font-semibold text-[var(--app-primary)]' : ''">
                <x-icon name="check" class="size-4" x-show="preference === '{{ $value }}'" />
                <span class="size-4" x-show="preference !== '{{ $value }}'" aria-hidden="true"></span>
                <span>{{ $optionLabel }}</span>
            </button>
        @endforeach
    </div>
</div>
