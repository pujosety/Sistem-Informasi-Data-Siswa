@props([
    'title' => null,
    'icon' => null,
    'description' => null,
    'class' => '',
    'bodyClass' => 'p-4 sm:p-5',
    'actions' => null,
])

<section {{ $attributes->merge(['class' => "surface $class"]) }}>
    @if ($title || $actions)
        <header class="flex items-start justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-[var(--app-border)]">
            <div class="min-w-0 flex items-start gap-2.5">
                @if ($icon)
                    <span class="shrink-0 text-[var(--app-text-muted)] mt-0.5">
                        <x-icon :name="$icon" class="w-[18px] h-[18px]" />
                    </span>
                @endif
                <div class="min-w-0">
                    <h2 class="text-h3 font-semibold text-[var(--app-text)]">{{ $title }}</h2>
                    @if ($description)
                        <p class="text-caption text-[var(--app-text-muted)] mt-0.5">{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="shrink-0 flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $bodyClass }}">{{ $slot }}</div>
</section>
