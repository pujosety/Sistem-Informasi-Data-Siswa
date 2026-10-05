@props(['section'])

{{--
    TESTIMONIALS — brief §9.

    THE LABEL MATTERS MORE THAN THE CARD.

    Brief §9 is explicit that fabricated testimonials must not be presented as
    fact. So when `is_sample` is set, the section carries a visible "contoh
    konten" badge ABOVE the cards, not a quiet caption underneath. A disclaimer
    below a wall of invented praise does not undo the praise; it just makes the
    page dishonest in one more place.

    A school with real quotes clears the flag in the CMS and the badge
    disappears.
--}}

@php
    $items  = $section->items('items');
    $sample = (bool) $section->value('is_sample', true);
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-bg)]">
        <div class="shell-wide py-20 sm:py-24">
            <div class="mx-auto max-w-2xl text-center">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Cerita dari Mereka' }}
                </h2>

                @if ($sample)
                    <p class="mt-5 inline-flex items-center gap-2 rounded-full border border-[var(--app-warning)]/30 bg-[var(--app-warning-soft)] px-3.5 py-1.5 text-caption font-medium text-[var(--app-warning)]">
                        <x-icon name="info" class="w-3.5 h-3.5" />
                        Contoh konten — ganti dengan testimoni asli melalui CMS
                    </p>
                @endif
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($items as $item)
                    <figure class="flex flex-col rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] p-6 transition-shadow hover:shadow-md">
                        <x-icon name="quote" class="w-7 h-7 text-[var(--app-primary)]/25" />

                        <blockquote class="mt-4 flex-1 text-small leading-relaxed text-[var(--app-text-muted)]">
                            {{ $item['quote'] ?? '' }}
                        </blockquote>

                        <figcaption class="mt-6 flex items-center gap-3 border-t border-[var(--app-border)] pt-5">
                            @if ($item['image'] ?? null)
                                <img src="{{ $item['image'] }}" alt="" class="size-10 rounded-full object-cover" loading="lazy">
                            @else
                                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-[var(--app-primary-soft)] text-small font-bold text-[var(--app-primary)]">
                                    {{ strtoupper(mb_substr($item['name'] ?? '?', 0, 1)) }}
                                </span>
                            @endif

                            <div class="min-w-0">
                                <p class="truncate text-small font-semibold text-[var(--app-text)]">{{ $item['name'] ?? '' }}</p>
                                <p class="truncate text-caption text-[var(--app-text-subtle)]">{{ $item['role'] ?? '' }}</p>
                            </div>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
