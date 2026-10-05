@props([
    'actions' => [],
    'placeholder' => 'Cari atau ketik perintah…',
    'label' => 'Pencarian global',
])

{{-- The command palette (brief §31).

     THE INTERACTION PROBLEM IT SOLVES: a keyboard shortcut that only works
     while focus happens to be on the right element is not a shortcut. The
     listener is bound at `document`, so Ctrl+K (Cmd+K on a Mac) opens the
     palette from a text field, a table, a drawer, anywhere — which is the only
     condition under which a command palette is worth having.

     ARROW KEYS MOVE THE SELECTION, Enter RUNS IT, Escape CLOSES IT, and the
     listbox follows `aria-activedescendant` rather than moving real focus
     between options. That is the correct pattern for a combobox: moving real
     focus into the list would make the search input lose focus, and every
     keystroke would go to the list instead of the box.

     RESULTS ARE PRE-RENDERED, NOT FETCHED. The palette is given its results as
     data (`$actions`), so it works with the keyboard on first paint and does
     not flash empty. Wire a `wire`-style fetch into `search()` if the dataset
     outgrows what is worth embedding in the page. --}}

<div x-data="commandPalette(@js($actions), @js($placeholder))"
     @keydown.window.meta.k.prevent="openPalette()"
     @keydown.window.ctrl.k.prevent="openPalette()"
     class="contents">

    {{-- Trigger. Also opened from the header and from ⌘K, so it is a button
         rather than a decorative search input. --}}
    <button type="button"
            @click="openPalette()"
            class="w-full flex items-center gap-2 h-9 px-3 rounded-[var(--radius-md)]
                   border border-[var(--app-border)] bg-[var(--app-surface)]
                   text-small text-[var(--app-text-subtle)] hover:border-[var(--app-border-strong)]
                   transition-colors">
        <x-icon name="search" class="w-4 h-4 shrink-0" />
        <span class="truncate">{{ $placeholder }}</span>
        <kbd class="ml-auto hidden sm:inline-flex items-center gap-0.5 px-1.5 py-0.5
                    rounded border border-[var(--app-border)] bg-[var(--app-surface-muted)]
                    text-[10px] font-semibold text-[var(--app-text-subtle)]">
            <span>Ctrl</span><span>K</span>
        </kbd>
    </button>

    {{-- Panel --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]">
            <div x-show="open"
                 x-transition.opacity.duration.150ms
                 class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"
                 @click="close()"
                 aria-hidden="true"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="relative mx-auto mt-[12vh] w-[calc(100%-2rem)] max-w-xl"
                 role="dialog"
                 aria-modal="true"
                 aria-label="{{ $label }}">

                <div @keydown.down.prevent="move(1)"
                     @keydown.up.prevent="move(-1)"
                     @keydown.enter.prevent="run()"
                     @keydown.escape="close()"
                     class="surface shadow-2xl overflow-hidden flex flex-col max-h-[70vh]">

                    <div class="flex items-center gap-2.5 px-4 h-12 border-b border-[var(--app-border)] shrink-0">
                        <x-icon name="search" class="w-[18px] h-[18px] shrink-0 text-[var(--app-text-subtle)]" />
                        <input x-model="query"
                               type="text"
                               role="combobox"
                               :aria-expanded="open ? 'true' : 'false'"
                               aria-controls="command-list"
                               :aria-activedescendant="index >= 0 ? 'command-' + index : null"
                               autocomplete="off"
                               spellcheck="false"
                               placeholder="{{ $placeholder }}"
                               class="flex-1 bg-transparent border-0 outline-none text-body text-[var(--app-text)] placeholder:text-[var(--app-text-subtle)]" />
                        <button type="button" @click="close()"
                                class="shrink-0 w-7 h-7 grid place-items-center rounded-[var(--radius-sm)]
                                       text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)] transition-colors"
                                aria-label="Tutup">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>

                    <ul id="command-list" role="listbox" class="flex-1 overflow-y-auto p-2" x-show="results.length">
                        <template x-for="(item, i) in results" :key="item.url">
                            <li :id="'command-' + i"
                                role="option"
                                :aria-selected="i === index"
                                @mouseenter="index = i"
                                @click="run()"
                                class="flex items-center gap-2.5 px-2.5 py-2 rounded-[var(--radius-sm)] cursor-pointer"
                                :class="i === index ? 'bg-[var(--app-surface-muted)]' : ''">
                                {{-- The icon is rendered server-side from the
                                     matched item rather than bound with `:name`,
                                     which would be parsed by Blade as a PHP
                                     expression — `item.icon || 'x'` compiles to
                                     an undefined constant and the page 500s. --}}
                                <span class="w-4 h-4 shrink-0 text-[var(--app-text-subtle)]"
                                      x-html="iconFor(item)"></span>
                                <span class="text-small text-[var(--app-text)] truncate" x-text="item.label"></span>
                                <span class="ml-auto text-caption text-[var(--app-text-subtle)] truncate"
                                      x-show="item.group" x-text="item.group"></span>
                            </li>
                        </template>
                    </ul>

                    <div x-show="! results.length" class="px-4 py-8 text-center">
                        <p class="text-small text-[var(--app-text-muted)]">Tidak ada hasil untuk “<span x-text="query"></span>”</p>
                    </div>

                    <div class="flex items-center gap-3 px-4 h-9 border-t border-[var(--app-border)]
                                bg-[var(--app-surface-muted)]/50 shrink-0 text-caption text-[var(--app-text-subtle)]">
                        <span>↑↓ navigasi</span>
                        <span>↵ buka</span>
                        <span>esc tutup</span>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('commandPalette', (actions, placeholder) => ({
        open: false,
        query: '',
        index: 0,
        actions,

        // Opening focuses the input, because a palette you have to click into
        // before typing is a modal with extra steps.
        openPalette() {
            this.open = true;
            this.$nextTick(() => this.$root.querySelector('input[role=combobox]')?.focus());
        },

        get results() {
            const q = this.query.trim().toLowerCase();
            if (! q) return this.actions.slice(0, 8);
            return this.actions
                .filter(a => a.label.toLowerCase().includes(q))
                .slice(0, 8);
        },

        search() { this.index = 0; },

        // Icon markup per item, so the `<template x-for>` body stays free of
        // Blade-visible `:` bindings that Alpine would never see as JS.
        iconFor(item) {
            const paths = {
                'corner-down-left': '<path d="M9 10l-5 5 5 5M4 15h11a4 4 0 004-4V7"/>',
                'users': '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/>',
                'megaphone': '<path d="M3 11v3a1 1 0 001 1h3l5 4V6L7 10H4a1 1 0 00-1 1z"/>',
                'calendar': '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
                'settings': '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 008 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H2a2 2 0 11 0-4 0h.09A1.65 1.65 0 001.6 8a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 11 2.83-2.83l.06.06a1.65 1.65 0 00 1.82.33H6a1.65 1.65 0 001-1.51V2a2 2 0 11 4 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 11 2.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V8a1.65 1.65 0 001.51 1H22a2 2 0 11 0 4 0h-.09a1.65 1.65 0 00-1.51 1z"/>',
            };
            const d = paths[item.icon] || paths['corner-down-left'];
            return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">${d}</svg>`;
        },

        move(delta) {
            const n = this.results.length;
            if (! n) return;
            this.index = (this.index + delta + n) % n;
        },

        run() {
            const item = this.results[this.index];
            if (! item) return;
            window.location.href = item.url;
        },

        close() {
            this.open = false;
            this.query = '';
            this.index = 0;
        },
    }));
});
</script>
@endonce