@props(['section'])

{{--
    PPDB — brief §10.

    The section the whole page exists to drive toward, so it is the largest
    block on the page and the only one with a full-bleed maroon field.

    WHY THE PERIOD AND CONTACT ARE IN THE BLOCK AND NOT JUST THE CTA: a
    prospective parent who clicks through to the PPDB page and then has to
    hunt for the deadline has already lost interest. The three facts that
    answer "can we still apply?" belong next to the button.
--}}

@php
    $period = $section->value('period');
    $items  = $section->items('highlights');
@endphp

<section class="relative isolate overflow-hidden bg-[var(--app-primary-dark)]">
    {{-- The supplied campus render, heavily overlaid: readable at any
         contrast, and it grounds the block in the actual school. --}}
    @if ($section->media?->url())
        <img src="{{ $section->media->url() }}"
             alt=""
             class="absolute inset-0 h-full w-full object-cover opacity-20"
             loading="lazy">
    @endif

    <div class="absolute inset-0 bg-gradient-to-r from-[var(--app-primary-dark)] via-[var(--app-primary)]/90 to-[var(--app-primary-alt)]/80"></div>

    <div class="relative shell-wide py-20 sm:py-24">
        <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">

            <div class="lg:col-span-7">
                @if ($section->subtitle)
                    <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-small font-medium text-white ring-1 ring-inset ring-white/25">
                        <span class="size-1.5 rounded-full bg-[var(--brand-gold)]"></span>
                        {{ $section->subtitle }}
                    </p>
                @endif

                <h2 class="mt-5 text-display font-bold leading-[1.1] tracking-tight text-white text-balance">
                    {{ $section->title ?? 'Siap Memulai Perjalananmu Bersama Kami?' }}
                </h2>

                @if ($section->body)
                    <p class="mt-5 max-w-xl text-body leading-relaxed text-white/80 sm:text-lg">
                        {{ $section->body }}
                    </p>
                @endif

                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ $section->value('cta_url', route('public.admission')) }}"
                       class="group inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] bg-white px-7 py-3.5 text-body font-semibold text-[var(--app-primary)] shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl sm:justify-start">
                        {{ $section->value('cta_label', 'Daftar Sekarang') }}
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>

                    @if ($section->value('secondary_cta_label'))
                        <a href="{{ $section->value('secondary_cta_url', route('public.admission')) }}"
                           class="inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] px-6 py-3.5 text-body font-semibold text-white ring-1 ring-inset ring-white/35 transition-colors hover:bg-white/10 sm:justify-start">
                            {{ $section->value('secondary_cta_label') }}
                        </a>
                    @endif
                </div>

                @if ($period)
                    <p class="mt-7 flex items-center gap-2 text-small text-white/70">
                        <x-icon name="calendar" class="w-4 h-4 shrink-0" />
                        {{ $period }}
                    </p>
                @endif
            </div>

            @if ($items !== [])
                <div class="lg:col-span-5">
                    <ul class="space-y-3 rounded-[var(--radius-lg)] border border-white/15 bg-white/10 p-6 backdrop-blur-sm">
                        @foreach ($items as $item)
                            <li class="flex items-start gap-3">
                                <x-icon :name="$item['icon'] ?? 'check'" class="mt-0.5 w-4 h-4 shrink-0 text-[var(--brand-gold)]" />
                                <span class="text-small leading-relaxed text-white/85">
                                    {{ $item['label'] ?? ($item['body'] ?? '') }}
                                </span>
                            </li>
                        @endforeach

                        @if ($section->value('contact'))
                            <li class="mt-5 flex items-start gap-3 border-t border-white/15 pt-5">
                                <x-icon name="phone" class="mt-0.5 w-4 h-4 shrink-0 text-white/60" />
                                <span class="text-small text-white/85">
                                    {{ $section->value('contact') }}
                                </span>
                            </li>
                        @endif
                    </ul>
                </div>
            @endif
        </div>
    </div>
</section>