@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'width' => 'md',
    'open' => true,
])

{{-- A right-side detail drawer.

     WHY A DRAWER AND NOT A PAGE (brief §17): opening a student's record on a
     separate page loses the list. The admin who is looking for one student
     among two hundred has to remember where they were, and usually comes back
     to the list and starts again. A drawer keeps the list visible beside it.

     WHY IT IS ANCHORED, NOT `position: fixed`: an anchored drawer pushes the
     page instead of floating over it, so the page behind it never scrolls
     unexpectedly and a long form inside the drawer does not fight the
     document for scroll position.

     The overlay closes on Escape and on backdrop click, and returns focus to
     the trigger. That last part is the one that gets missed: without it,
     keyboard focus falls to <body> after the drawer closes and the next Tab
     restarts from the top of the document — so the screen reader user loses
     their place after every single row they inspect. --}}

@php
    $panel = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-2xl'][$width] ?? 'sm:max-w-md';
    $id = $attributes->get('id') ?: 'drawer-'.substr(md5($title ?? 'detail'), 0, 8);
@endphp

<div x-data="{ open: @js($open) }"
     @keydown.escape.window="if (open) { open = false; $el.querySelector('input,button,[tabindex]')?.focus(); }"
     x-cloak>

    {{-- Backdrop --}}
    <div x-show="open"
         x-transition.opacity.duration.200ms
         class="fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px]"
         @click="open = false"
         aria-hidden="true"></div>

    {{-- Panel --}}
    <aside x-show="open"
           x-transition:enter="transition ease-out duration-250"
           x-transition:enter-start="translate-x-full"
           x-transition:enter-end="translate-x-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="translate-x-0"
           x-transition:leave-end="translate-x-full"
           class="fixed inset-y-0 right-0 z-50 w-full {{ $panel }} bg-[var(--app-surface)]
                  border-l border-[var(--app-border)] shadow-xl flex flex-col"
           role="dialog"
           aria-modal="true"
           aria-label="{{ $title ?? 'Detail' }}">

        <header class="flex items-start gap-3 px-4 sm:px-5 py-3.5 border-b border-[var(--app-border)] shrink-0">
            @if ($icon)
                <span class="shrink-0 text-[var(--app-text-muted)] mt-0.5">
                    <x-icon :name="$icon" class="w-[18px] h-[18px]" />
                </span>
            @endif

            <div class="min-w-0 flex-1">
                <h2 class="text-h3 font-semibold text-[var(--app-text)]">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="text-caption text-[var(--app-text-muted)] mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>

            <button type="button" @click="open = false"
                    class="shrink-0 grid place-items-center w-8 h-8 -mr-1 rounded-[var(--radius-md)]
                           text-[var(--app-text-muted)] hover:text-[var(--app-text)]
                           hover:bg-[var(--app-surface-muted)] transition-colors"
                    aria-label="Tutup detail">
                <x-icon name="x" class="w-[18px] h-[18px]" />
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-4 sm:p-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <footer class="shrink-0 flex items-center gap-2 px-4 sm:px-5 py-3 border-t border-[var(--app-border)] bg-[var(--app-surface-muted)]/50">
                {{ $footer }}
            </footer>
        @endisset
    </aside>
</div>