@props(['label' => null, 'value' => null, 'icon' => 'info', 'tone' => 'brand', 'hint' => null, 'href' => null, 'trend' => null])

@php
    $tones = [
        'brand'   => 'bg-brand-50 text-brand-700',
        'success' => 'bg-[var(--app-success-soft)] text-[var(--app-success)]',
        'warning' => 'bg-[var(--app-warning-soft)] text-[oklch(0.48_0.12_70)]',
        'danger'  => 'bg-[var(--app-danger-soft)] text-[var(--app-danger)]',
        'info'    => 'bg-[var(--app-info-soft)] text-[var(--app-info)]',
        'neutral' => 'bg-ink-100 text-ink-600',
    ];
    $toneClass = $tones[$tone] ?? $tones['brand'];
    $tag = $href ? 'a' : 'div';

    // Guard against non-scalar props: a raw array here fatals on echo.
    $asText = fn ($v) => is_array($v) ? implode(', ', array_map(fn ($i) => is_scalar($i) ? (string) $i : '', $v))
        : (is_object($v) && method_exists($v, '__toString') ? (string) $v : $v);
    $labelText = $asText($label);
    $hintText = $asText($hint);
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'surface p-4 sm:p-5 flex items-start gap-3']) }}
>
    <span class="shrink-0 w-9 h-9 rounded-[var(--radius-md)] grid place-items-center {{ $toneClass }}">
        <x-icon :name="$icon" class="w-[18px] h-[18px]" />
    </span>

    <div class="min-w-0 flex-1">
        <p class="text-caption text-[var(--app-text-muted)] font-medium truncate">{{ $labelText }}</p>
        <p class="mt-0.5 text-h2 font-bold text-[var(--app-text)] tabular-nums leading-tight">
            {{ is_numeric($value) ? number_format((float) $value) : ($asText($value) ?: '—') }}
        </p>
        @if ($hintText)
            <p class="mt-1 text-caption text-[var(--app-text-subtle)] truncate">{{ $hintText }}</p>
        @endif
    </div>

    @if ($trend !== null)
        <span class="shrink-0 text-caption font-semibold {{ $trend >= 0 ? 'text-[var(--app-success)]' : 'text-[var(--app-danger)]' }}">
            {{ $trend >= 0 ? '+' : '' }}{{ $trend }}%
        </span>
    @endif
</{{ $tag }}>
