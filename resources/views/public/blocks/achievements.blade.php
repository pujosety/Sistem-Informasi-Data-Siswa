@props(['section'])

{{--
    ACHIEVEMENTS — brief §7.

    Big numbers first, then the cards. The order is deliberate: a visitor
    skimming for "is this school any good" reads the figures, and the cards are
    for the parent who wants specifics.

    A count that nobody can verify is decoration. Every figure here comes from
    CMS content an administrator typed, so the section shows what the school
    claims, and the claim is the school's to make.
--}}

@php
    $stats = $section->items('stats');
    $cards = $section->items('cards');
@endphp

@if ($stats || $cards)
    <section class="bg-[var(--app-surface)]">
        <div class="shell-wide py-20 sm:py-24">

            <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    @if ($section->subtitle)
                        <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                    @endif
                    <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                        {{ $section->title ?? 'Prestasi yang Membanggakan' }}
                    </h2>
                    @if ($section->body)
                        <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                    @endif
                </div>

                <div class="lg:col-span-8">
                    @if ($stats)
                        <dl class="grid grid-cols-2 gap-x-6 gap-y-8 sm:grid-cols-4">
                            @foreach ($stats as $stat)
                                <div class="border-l-2 border-[var(--app-primary)] pl-4">
                                    <dd class="text-3xl font-bold tabular-nums text-[var(--app-text)] sm:text-4xl">
                                        {{ $stat['value'] ?? '' }}@if ($stat['suffix'] ?? null)<span class="text-[var(--app-primary)]">{{ $stat['suffix'] }}</span>@endif
                                    </dd>
                                    <dt class="mt-1.5 text-small text-[var(--app-text-muted)]">{{ $stat['label'] ?? '' }}</dt>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($cards)
                        <div class="mt-10 grid gap-4 sm:grid-cols-2">
                            @foreach ($cards as $card)
                                <article class="group flex gap-4 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-bg)] p-5 transition-all hover:border-[var(--app-primary)]/30 hover:shadow-md">
                                    @if ($card['image'] ?? null)
                                        <img src="{{ $card['image'] }}"
                                             alt="{{ $card['title'] ?? '' }}"
                                             class="size-16 shrink-0 rounded-[var(--radius-sm)] object-cover"
                                             loading="lazy">
                                    @else
                                        <span class="grid size-16 shrink-0 place-items-center rounded-[var(--radius-sm)] bg-[var(--brand-gold)]/12 text-[var(--brand-gold)]">
                                            <x-icon name="trophy" class="w-7 h-7" />
                                        </span>
                                    @endif

                                    <div class="min-w-0">
                                        @if ($card['level'] ?? null)
                                            <p class="text-caption font-semibold uppercase tracking-wide text-[var(--app-primary)]">{{ $card['level'] }}</p>
                                        @endif
                                        <h3 class="mt-0.5 text-small font-semibold leading-snug text-[var(--app-text)]">{{ $card['title'] ?? '' }}</h3>
                                        @if ($card['year'] ?? null)
                                            <p class="mt-1 text-caption text-[var(--app-text-subtle)]">{{ $card['year'] }}</p>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
