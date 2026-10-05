@props(['section'])

{{--
    CLOSING CTA — brief §15.

    Maroon field, one message, two ways to act. It is the last thing on the
    page, so it earns the space: a visitor who scrolled past the PPDB block
    without clicking gets one more chance, and one who did not scroll this far
    is being given a summary anyway.

    No image by default. The block after a full-bleed PPDB section already sits
    on a photograph; another one makes the bottom of the page a wall of imagery
    with no resting point.
--}}

<section class="bg-[var(--app-surface)]">
    <div class="shell-wide py-20 sm:py-28">
        <div class="relative mx-auto max-w-4xl overflow-hidden rounded-[var(--radius-lg)] bg-[var(--app-primary-dark)] px-6 py-14 text-center sm:px-14 sm:py-20">

            @if ($section->media?->url())
                <img src="{{ $section->media->url() }}"
                     alt=""
                     class="absolute inset-0 h-full w-full object-cover opacity-20"
                     loading="lazy">
                <div class="absolute inset-0 bg-[var(--app-primary-dark)]/70"></div>
            @endif

            <div class="relative">
                <h2 class="text-display font-bold leading-[1.12] tracking-tight text-white text-balance">
                    {{ $section->title ?? 'Masa Depan Dimulai dari Pilihan Hari Ini.' }}
                </h2>

                @if ($section->body)
                    <p class="mx-auto mt-5 max-w-xl text-body leading-relaxed text-white/75">
                        {{ $section->body }}
                    </p>
                @endif

                <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <a href="{{ $section->value('cta_url', route('public.admission')) }}"
                       class="group inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] bg-white px-7 py-3.5 text-body font-semibold text-[var(--app-primary)] shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl">
                        {{ $section->value('cta_label', 'Daftar Sekarang') }}
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>

                    @if ($section->value('secondary_cta_label'))
                        <a href="{{ $section->value('secondary_cta_url', route('public.contact')) }}"
                           class="inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] px-6 py-3.5 text-body font-semibold text-white ring-1 ring-inset ring-white/35 transition-colors hover:bg-white/10">
                            {{ $section->value('secondary_cta_label') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>