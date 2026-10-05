@props(['section'])

{{--
    NEWS — brief §11: one featured story plus three secondary cards.

    NOT a four-up blog grid. The brief is explicit, and it is right: four equal
    cards give every story the same weight, which means none of them has any.
    One large lead and three small ones also tells a visitor where to look
    first.
--}}

@php
    $items = $section->items('items');
    $lead  = array_shift($items);
@endphp

@if ($lead || $items !== [])
    <section class="bg-[var(--app-surface)]">
        <div class="shell-wide py-20 sm:py-24">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    @if ($section->subtitle)
                        <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                    @endif
                    <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                        {{ $section->title ?? 'Berita & Kegiatan' }}
                    </h2>
                </div>

                @if ($section->value('cta_label'))
                    <a href="{{ $section->value('cta_url', route('public.news')) }}"
                       class="group inline-flex shrink-0 items-center gap-2 font-semibold text-[var(--app-primary)]">
                        <span class="relative after:absolute after:-bottom-0.5 after:left-0 after:h-px after:w-full after:origin-left after:bg-current after:transition-transform hover:after:scale-x-0 hover:after:origin-right">{{ $section->value('cta_label') }}</span>
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>
                @endif
            </div>

            <div class="mt-12 grid gap-6 lg:grid-cols-3">
                @if ($lead)
                    <article class="group lg:col-span-2">
                        <a href="{{ $lead['url'] ?? '#' }}" class="block">
                            @if ($lead['image'] ?? null)
                                <img src="{{ $lead['image'] }}"
                                     alt="{{ $lead['title'] ?? '' }}"
                                     class="aspect-16/9 w-full rounded-[var(--radius-md)] object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                                     loading="lazy">
                            @else
                                <div class="grid aspect-16/9 w-full place-items-center rounded-[var(--radius-md)] bg-[var(--app-primary-soft)]">
                                    <x-icon name="newspaper" class="w-12 h-12 text-[var(--app-primary)]/30" />
                                </div>
                            @endif

                            <div class="mt-5">
                                @if ($lead['category'] ?? null)
                                    <span class="badge badge-brand">{{ $lead['category'] }}</span>
                                @endif
                                <h3 class="mt-3 text-h2 font-bold leading-snug text-[var(--app-text)] transition-colors group-hover:text-[var(--app-primary)]">
                                    {{ $lead['title'] ?? '' }}
                                </h3>
                                @if ($lead['excerpt'] ?? null)
                                    <p class="mt-2 line-clamp-2 text-body text-[var(--app-text-muted)]">{{ $lead['excerpt'] }}</p>
                                @endif
                                @if ($lead['date'] ?? null)
                                    <time class="mt-3 block text-caption text-[var(--app-text-subtle)]">{{ $lead['date'] }}</time>
                                @endif
                            </div>
                        </a>
                    </article>
                @endif

                @if ($items !== [])
                    <div class="space-y-6">
                        @foreach (array_slice($items, 0, 3) as $item)
                            <article class="group">
                                <a href="{{ $item['url'] ?? '#' }}" class="flex gap-4">
                                    @if ($item['image'] ?? null)
                                        <img src="{{ $item['image'] }}"
                                             alt="{{ $item['title'] ?? '' }}"
                                             class="size-24 shrink-0 rounded-[var(--radius-sm)] object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                                             loading="lazy">
                                    @else
                                        <span class="grid size-24 shrink-0 place-items-center rounded-[var(--radius-sm)] bg-[var(--app-surface-muted)]">
                                            <x-icon name="file-text" class="w-7 h-7 text-[var(--app-text-subtle)]" />
                                        </span>
                                    @endif

                                    <div class="min-w-0">
                                        @if ($item['category'] ?? null)
                                            <p class="text-caption font-semibold text-[var(--app-primary)]">{{ $item['category'] }}</p>
                                        @endif
                                        <h3 class="mt-1 line-clamp-2 text-small font-semibold leading-snug text-[var(--app-text)] transition-colors group-hover:text-[var(--app-primary)]">
                                            {{ $item['title'] ?? '' }}
                                        </h3>
                                        @if ($item['date'] ?? null)
                                            <time class="mt-1.5 block text-caption text-[var(--app-text-subtle)]">{{ $item['date'] }}</time>
                                        @endif
                                    </div>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
