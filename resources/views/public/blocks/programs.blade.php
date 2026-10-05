@props(['section'])

{{--
    PROGRAMS — brief §5, "asymmetrical grid so it does not become monotonous".

    The asymmetry is real, not decorative: the first program is a large editorial
    card spanning two columns and two rows, and the rest are compact. That is
    what stops six programmes reading as six identical tiles.

    It is also a hierarchy, which is the honest reason to do it — the lead
    programme genuinely is the school's flagship, and giving it more space says
    so without a label.
--}}

@php
    $items = $section->items('items');
    $lead  = array_shift($items);
@endphp

@if ($lead || $items !== [])
    <section class="bg-[var(--app-bg)]">
        <div class="shell-wide py-20 sm:py-24">

            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    @if ($section->subtitle)
                        <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">
                            {{ $section->subtitle }}
                        </p>
                    @endif
                    <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                        {{ $section->title ?? 'Program Unggulan' }}
                    </h2>
                </div>

                @if ($section->value('cta_label'))
                    <a href="{{ $section->value('cta_url', route('public.programs')) }}"
                       class="group inline-flex shrink-0 items-center gap-2 font-semibold text-[var(--app-primary)]">
                        <span class="relative after:absolute after:-bottom-0.5 after:left-0 after:h-px after:w-full after:origin-left after:bg-current after:transition-transform hover:after:scale-x-0 hover:after:origin-right">
                            {{ $section->value('cta_label') }}
                        </span>
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>
                @endif
            </div>

            @if ($section->body)
                <p class="mt-4 max-w-2xl text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
            @endif

            {{-- Lead card: 2 cols × 2 rows on desktop. --}}
            @if ($lead)
                @php $leadImage = $lead['image'] ?? null; @endphp

                <article class="group relative mt-12 overflow-hidden rounded-[var(--radius-lg)] border border-[var(--app-border)] bg-[var(--app-primary-dark)]">
                    <div class="grid lg:grid-cols-2">
                        <div class="order-2 flex flex-col justify-center p-8 sm:p-10 lg:order-1">
                            @if ($lead['eyebrow'] ?? null)
                                <p class="text-small font-semibold uppercase tracking-widest text-[var(--brand-gold)]">
                                    {{ $lead['eyebrow'] }}
                                </p>
                            @endif

                            <h3 class="mt-2 text-h1 font-bold text-white text-balance">
                                {{ $lead['title'] ?? '' }}
                            </h3>

                            @if ($lead['body'] ?? null)
                                <p class="mt-4 text-body leading-relaxed text-white/75">
                                    {{ $lead['body'] }}
                                </p>
                            @endif

                            @if ($lead['cta_label'] ?? null)
                                <a href="{{ $lead['cta_url'] ?? route('public.programs') }}"
                                   class="group/btn mt-7 inline-flex w-fit items-center gap-2 rounded-[var(--radius-md)] bg-white px-5 py-2.5 text-small font-semibold text-[var(--app-primary)] transition-transform hover:-translate-y-0.5">
                                    {{ $lead['cta_label'] }}
                                    <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover/btn:translate-x-1" />
                                </a>
                            @endif
                        </div>

                        <div class="order-1 lg:order-2">
                            @if ($leadImage)
                                <img src="{{ $leadImage }}"
                                     alt="{{ $lead['title'] ?? 'Program sekolah' }}"
                                     class="h-56 w-full object-cover transition-transform duration-500 group-hover:scale-[1.02] sm:h-72 lg:h-full"
                                     loading="lazy">
                            @else
                                <div class="grid h-56 w-full place-items-center bg-gradient-to-br from-[var(--app-primary)] to-[var(--app-primary-dark)] sm:h-72 lg:h-full">
                                    <x-icon :name="$lead['icon'] ?? 'flask-conical'" class="w-16 h-16 text-white/25" />
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @endif

            {{-- Compact cards. --}}
            @if ($items !== [])
                <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($items as $item)
                        @php $image = $item['image'] ?? null; @endphp

                        <article class="group overflow-hidden rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] transition-all duration-200 hover:-translate-y-1 hover:shadow-lg">
                            @if ($image)
                                <img src="{{ $image }}"
                                     alt="{{ $item['title'] ?? 'Program sekolah' }}"
                                     class="aspect-16/10 w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                     loading="lazy">
                            @else
                                <div class="grid aspect-16/10 w-full place-items-center bg-[var(--app-primary-soft)]">
                                    <x-icon :name="$item['icon'] ?? 'sparkles'" class="w-10 h-10 text-[var(--app-primary)]/40" />
                                </div>
                            @endif

                            <div class="p-6">
                                <h3 class="text-h3 font-semibold text-[var(--app-text)]">{{ $item['title'] ?? '' }}</h3>
                                @if ($item['body'] ?? null)
                                    <p class="mt-2 text-small leading-relaxed text-[var(--app-text-muted)] line-clamp-3">{{ $item['body'] }}</p>
                                @endif
                                @if ($item['cta_label'] ?? null)
                                    <a href="{{ $item['cta_url'] ?? route('public.programs') }}"
                                       class="group/link mt-4 inline-flex items-center gap-1.5 text-small font-semibold text-[var(--app-primary)]">
                                        {{ $item['cta_label'] }}
                                        <x-icon name="arrow-right" class="w-3.5 h-3.5 transition-transform group-hover/link:translate-x-1" />
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endif