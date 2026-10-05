@props(['section'])

{{--
    ABOUT — brief §3, split layout.

    WHY THE TEXT COLUMN IS NARROWER THAN THE IMAGE: brief §DESKTOP asks for
    asymmetric composition. An even two-column split is the shape every school
    website reaches for by default, and it reads as a template. The 5/7 ratio
    gives the photograph the page, which is also true — the campus is the
    evidence, the paragraph is the claim.
--}}

@php
    $image = $section->media?->url();
    $cta   = $section->value('cta_label', 'Kenali Sekolah Kami');
@endphp

<section class="bg-[var(--app-bg)]">
    <div class="shell-wide py-20 sm:py-24">

        <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-16">

            {{-- Text: 5 of 12 --}}
            <div class="lg:col-span-5">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">
                        {{ $section->subtitle }}
                    </p>
                @endif

                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Lebih dari Sekadar Tempat Belajar' }}
                </h2>

                @if ($section->body)
                    <div class="mt-5 space-y-4 text-body leading-relaxed text-[var(--app-text-muted)]">
                        @foreach (preg_split('/\n\s*\n/', trim($section->body)) as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @endif

                @if ($cta)
                    <a href="{{ $section->value('cta_url', route('public.about')) }}"
                       class="group mt-8 inline-flex items-center gap-2 font-semibold text-[var(--app-primary)]">
                        <span class="relative after:absolute after:-bottom-0.5 after:left-0 after:h-px after:w-full after:origin-left after:scale-x-100 after:bg-current after:transition-transform hover:after:origin-right hover:after:scale-x-0">
                            {{ $cta }}
                        </span>
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>
                @endif
            </div>

            {{-- Image: 7 of 12, overlapping the text column slightly. --}}
            <div class="lg:col-span-7">
                <div class="relative">
                    @if ($image)
                        <img src="{{ $image }}"
                             alt="{{ $section->title ? 'Kegiatan di '.$section->title : 'Kegiatan sekolah' }}"
                             class="aspect-4/3 w-full rounded-[var(--radius-lg)] object-cover shadow-sm"
                             loading="lazy">
                    @else
                        {{-- No image configured: the layout still holds. A reserved
                             slot with the school mark beats a collapsed box that
                             shifts everything below it. --}}
                        <div class="grid aspect-4/3 w-full place-items-center rounded-[var(--radius-lg)] bg-[var(--app-primary-soft)]">
                            <img src="{{ asset(config('branding.assets.mark_globe')) }}"
                                 alt=""
                                 class="w-24 opacity-40">
                        </div>
                    @endif

                    {{-- Overlapping accent card — the overlap is what stops this
                         reading as a stock two-column template. --}}
                    @if ($section->value('highlight_value'))
                        <div class="absolute -bottom-6 -left-4 hidden rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] px-5 py-4 shadow-lg sm:block lg:-left-8">
                            <p class="text-2xl font-bold tabular-nums text-[var(--app-primary)]">
                                {{ $section->value('highlight_value') }}
                            </p>
                            <p class="mt-0.5 text-caption text-[var(--app-text-muted)]">
                                {{ $section->value('highlight_label') }}
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>