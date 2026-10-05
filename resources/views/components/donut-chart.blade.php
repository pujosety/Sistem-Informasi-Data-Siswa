@props([
    'items' => [],
    'size' => 200,
    'stroke' => 26,
    'centerLabel' => 'Total',
])

{{-- A donut chart built from SVG circles (brief §9).

     REIMPLEMENTED, NOT INSTALLED. The 21st.dev reference is a React component
     using framer-motion and `stroke-dasharray` on nested circles. The geometry
     is the same here; what is dropped is the enter/exit choreography, which was
     decoration rather than function — and the whole reason this component
     works at all is that it is server-rendered.

     WHY THE MATH IS PRECOMPUTED HERE RATHER THAN IN JAVASCRIPT: a chart that
     only appears once JS has measured the DOM is a chart that flashes empty on
     a slow connection and does not appear at all without JS. Every offset and
     length is a plain PHP number in the markup.

     Each segment is a <circle> with stroke-dasharray, so the accessible name
     has to come from elsewhere: the legend is the real content, and the centre
     total is text, not SVG. --}}

@php
    $total = collect($items)->sum('value');
    $radius = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $radius;

    // Segment colours come from the theme, in a fixed order, so the same chart
    // is the same colours everywhere. The palette is deliberately the STATUS
    // ramp: in this application a colour already means something (green is
    // "verified"), and reusing brand blue for an arbitrary slice would make a
    // donut about gender look like a donut about approval.
    $palette = [
        'var(--app-primary)',
        'var(--app-info)',
        'var(--app-success)',
        'var(--app-warning)',
        'var(--app-danger)',
        'var(--app-text-subtle)',
    ];

    // Each segment gets its own dash pattern on the same circle. `offset` runs
    // backwards along the path, so it is accumulated as a negative value.
    $offset = 0.0;
    $segments = collect($items)->map(function ($item, $index) use ($total, $circumference, &$offset, $palette) {
        $share = $total > 0 ? ($item['value'] / $total) : 0;
        $length = $share * $circumference;

        $segment = [
            'color' => $item['color'] ?? $palette[$index % count($palette)],
            'dash' => $length.' '.($circumference - $length),
            'offset' => -$offset,
            'label' => $item['label'] ?? '',
            'value' => $item['value'] ?? 0,
            'percent' => round($share * 100),
        ];

        $offset += $length;

        return $segment;
    });
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center']) }}>

    <div class="relative shrink-0" style="width: {{ $size }}px; height: {{ $size }}px;">
        <svg viewBox="0 0 {{ $size }} {{ $size }}"
             width="{{ $size }}" height="{{ $size }}"
             role="img"
             aria-label="{{ collect($segments)->map(fn ($s) => $s['label'].' '.$s['value'].' ('.$s['percent'].'%)')->join(', ') }}">

            {{-- Track: the empty ring behind the data, so a 30%-filled donut
                 still reads as a donut rather than as an arc. --}}
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                    fill="none" stroke="var(--app-surface-muted)" stroke-width="{{ $stroke }}" />

            @foreach ($segments as $segment)
                @if ($segment['value'] > 0)
                    <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}"
                            fill="none"
                            stroke="{{ $segment['color'] }}"
                            stroke-width="{{ $stroke }}"
                            stroke-dasharray="{{ $segment['dash'] }}"
                            stroke-dashoffset="{{ $segment['offset'] }}"
                            transform="rotate(-90 {{ $size / 2 }} {{ $size / 2 }})">
                        <title>{{ $segment['label'] }}: {{ $segment['value'] }} ({{ $segment['percent'] }}%)</title>
                    </circle>
                @endif
            @endforeach
        </svg>

        {{-- Centre: text, so it is readable, selectable and screen-reader
             accessible. The reference centres a React node that swaps on hover;
             the static version shows the total, which is the number the reader
             actually wants at a glance. --}}
        <div class="absolute inset-0 grid place-content-center text-center">
            <p class="text-2xl font-semibold tabular-nums text-[var(--app-text)]">{{ number_format($total, 0, ',', '.') }}</p>
            <p class="text-caption text-[var(--app-text-muted)]">{{ $centerLabel }}</p>
        </div>
    </div>

    {{-- Legend. Clicking a row is not wired up on purpose: a legend that looks
         interactive but does nothing is worse than a plain one. --}}
    <dl class="mt-4 w-full space-y-1.5">
        @foreach ($segments as $segment)
            <div class="flex items-center gap-2.5">
                <span class="size-2.5 rounded-full shrink-0"
                      style="background-color: {{ $segment['color'] }}"
                      aria-hidden="true"></span>
                <dt class="text-small text-[var(--app-text-muted)] truncate">{{ $segment['label'] }}</dt>
                <dd class="ml-auto text-small font-semibold tabular-nums text-[var(--app-text)]">
                    {{ number_format($segment['value'], 0, ',', '.') }}
                    <span class="text-caption font-normal text-[var(--app-text-subtle)]">{{ $segment['percent'] }}%</span>
                </dd>
            </div>
        @endforeach
    </dl>
</div>