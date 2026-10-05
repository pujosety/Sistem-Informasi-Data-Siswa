@props([
    'rows' => 4,
    'type' => 'text',
    'class' => '',
])

{{-- Loading placeholder (brief §34).

     A skeleton is shaped like the content it stands in for. A generic grey bar
     repeated five times tells the reader nothing about whether a table is
     loading or a paragraph is loading, and a spinner — the thing this replaces —
     tells them nothing at all and makes the page jump when it vanishes.

     The shimmer is a single background-position transition rather than an
     animated gradient, so `prefers-reduced-motion` (already handled globally in
     app.css) removes it without a second rule here. --}}

<div {{ $attributes->merge(['class' => "animate-pulse space-y-3 $class"]) }}
     role="status"
     aria-label="Memuat">

    @if ($type === 'table')
        <div class="space-y-2">
            <div class="h-8 rounded-[var(--radius-sm)] bg-[var(--app-surface-muted)] w-full"></div>
            @for ($i = 0; $i < $rows; $i++)
                <div class="h-12 rounded-[var(--radius-sm)] bg-[var(--app-surface-muted)]/70 w-full"></div>
            @endfor
        </div>
    @elseif ($type === 'card')
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @for ($i = 0; $i < $rows; $i++)
                <div class="surface p-4 space-y-2">
                    <div class="h-3 w-1/3 rounded bg-[var(--app-surface-muted)]"></div>
                    <div class="h-6 w-1/2 rounded bg-[var(--app-surface-muted)]"></div>
                </div>
            @endfor
        </div>
    @else
        @for ($i = 0; $i < $rows; $i++)
            <div class="h-4 rounded bg-[var(--app-surface-muted)]{{ $i === $rows - 1 ? ' w-2/3' : ' w-full' }}"></div>
        @endfor
    @endif

    <span class="sr-only">Memuat…</span>
</div>