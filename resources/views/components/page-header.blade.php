@props([
    'title' => null,
    'description' => null,
    'back' => null,
    'icon' => null,
])

@php
    // Yielded Blade sections can arrive entity-encoded through a component
    // attribute. Decode once, then escape at render time so "&" stays "&".
    $headingText = html_entity_decode(strip_tags(is_array($title) ? implode(' ', $title) : (string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $descText = html_entity_decode(strip_tags(is_array($description) ? implode(' ', $description) : (string) ($description ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
@endphp

<div {{ $attributes->merge(['class' => 'mb-5 sm:mb-6']) }}>
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="min-w-0 flex items-start gap-3">
            @if ($back)
                <a href="{{ $back }}"
                   class="shrink-0 grid place-items-center w-9 h-9 mt-0.5 rounded-[var(--radius-md)] border border-[var(--app-border-strong)] text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)] transition-colors"
                   aria-label="Kembali">
                    <x-icon name="arrow-left" class="w-4 h-4" />
                </a>
            @elseif ($icon)
                <span class="shrink-0 grid place-items-center w-10 h-10 rounded-[var(--radius-md)] bg-brand-50 text-brand-700 mt-0.5">
                    <x-icon :name="$icon" class="w-5 h-5" />
                </span>
            @endif

            <div class="min-w-0">
                <h1 class="text-h1 font-bold text-[var(--app-text)]">{{ $headingText }}</h1>
                @if ($descText)
                    <p class="mt-1 text-body text-[var(--app-text-muted)]">{{ $descText }}</p>
                @endif
                @isset($slot)
                    {{ $slot }}
                @endisset
            </div>
        </div>

        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 shrink-0 sm:pt-1">{{ $actions }}</div>
        @endisset
    </div>
</div>
