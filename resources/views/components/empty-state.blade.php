@props([
    'icon' => 'inbox',
    'title' => 'Belum ada data',
    'description' => null,
    'action' => null,
    'compact' => false,
])

<div {{ $attributes->merge(['class' => 'text-center']) }}>
    <span class="mx-auto grid place-items-center w-12 h-12 rounded-[var(--radius-lg)] bg-ink-100 text-[var(--app-text-subtle)] {{ $compact ? 'mb-3' : 'mb-4' }}">
        <x-icon :name="$icon" class="w-6 h-6" />
    </span>
    <h3 class="text-h3 font-semibold text-[var(--app-text)]">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 text-body text-[var(--app-text-muted)] max-w-sm mx-auto">{{ $description }}</p>
    @endif
    @if ($action)
        <div class="mt-4 flex justify-center gap-2">{{ $action }}</div>
    @endif
</div>
