@props([
    'title' => null,
    'description' => null,
    'action' => null,
    'height' => 'h-52',
    'loading' => false,
])

{{-- The frame every chart sits in (brief §9).

     WHY A FRAME AND NOT A BARE <svg>: the five chart types in the brief —
     area, donut, sparkline, bar, heatmap — all want the same things, and
     without a shared frame each one reinvents them slightly differently. That
     drift is what makes a dashboard feel assembled rather than designed: four
     charts, four title sizes, three paddings.

     The heading is a real <h2> and the plot is wrapped in a labelled region,
     so a screen reader hears "Enrolment trend, chart" rather than reading five
     hundred SVG path coordinates aloud. --}}

<x-card :title="null" body-class="p-0" {{ $attributes->only('class') }}>

    <header class="flex items-start justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-[var(--app-border)]">
        <div class="min-w-0">
            @if ($title)
                <h2 class="text-h3 font-semibold text-[var(--app-text)]">{{ $title }}</h2>
            @endif
            @if ($description)
                <p class="text-caption text-[var(--app-text-muted)] mt-0.5">{{ $description }}</p>
            @endif
        </div>

        @isset($actions)
            <div class="shrink-0 flex items-center gap-2">{{ $actions }}</div>
        @endisset
    </header>

    <div class="p-4 sm:p-5">
        @if ($loading)
            <x-loading-skeleton :rows="4" />
        @else
            <figure role="img" aria-label="{{ $description ?? $title ?? 'Grafik' }}">
                <div class="{{ $height }} w-full">{{ $slot }}</div>

                {{-- A chart's actual numbers, as text. Every chart in this
                     application must have one of these: the visual is a summary
                     for people who can see it, and the numbers are the content
                     for everyone else. --}}
                @isset($summary)
                    <figcaption class="sr-only">{{ $summary }}</figcaption>
                @endisset
            </figure>

            @isset($legend)
                <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    {{ $legend }}
                </div>
            @endisset
        @endif
    </div>
</x-card>