@props([
    'title' => null,
    'message' => null,
    'variant' => 'error',
    'action' => null,
])

@php
    $map = [
        'error'   => ['icon' => 'alert-circle',   'cls' => 'border-[var(--app-danger)] bg-[var(--app-danger-soft)] text-[var(--app-danger)]'],
        'warning' => ['icon' => 'alert-triangle', 'cls' => 'border-[oklch(0.85_0.09_80)] bg-[var(--app-warning-soft)] text-[oklch(0.45_0.11_70)]'],
        'success' => ['icon' => 'check-circle',   'cls' => 'border-[oklch(0.82_0.08_155)] bg-[var(--app-success-soft)] text-[var(--app-success)]'],
        'info'    => ['icon' => 'info',           'cls' => 'border-[oklch(0.85_0.06_230)] bg-[var(--app-info-soft)] text-[var(--app-info)]'],
    ];
    $v = $map[$variant] ?? $map['error'];
    $vIcon = $v['icon'];
    $vClass = $v['cls'];

    // Defensive: callers occasionally pass a collection/array (e.g. a count or
    // a list) where a string was expected, which fatals on htmlspecialchars().
    $text = match (true) {
        $message === null => null,
        is_array($message) => implode(', ', array_map(fn ($m) => is_scalar($m) ? (string) $m : '', $message)),
        is_bool($message)  => $message ? 'Ya' : 'Tidak',
        is_object($message) && method_exists($message, '__toString') => (string) $message,
        default            => (string) $message,
    };

    $heading = is_array($title) ? implode(' ', $title) : (string) $title;
@endphp

<div role="alert"
     {{ $attributes->merge(['class' => 'rounded-[var(--radius-lg)] border px-4 py-3 flex items-start gap-3 '.$vClass]) }}>
    <x-icon :name="$vIcon" class="w-[18px] h-[18px] shrink-0 mt-0.5" />
    <div class="min-w-0 flex-1 text-body">
        @if ($heading)
            <p class="font-semibold">{{ $heading }}</p>
        @endif
        @if ($text)
            <p class="mt-0.5 opacity-90">{{ $text }}</p>
        @endif
        {{ $slot }}
    </div>
    @if ($action)
        <div class="shrink-0">{{ $action }}</div>
    @endif
</div>
