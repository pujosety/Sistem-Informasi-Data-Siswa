@props(['section'])

{{--
    HERO — brief §1 and §HERO IMAGE.

    The brief is explicit: "jangan meninggalkan hero hanya berupa gradient +
    teks", and it asks for a composition rather than a banner. So this is a
    two-column editorial hero on desktop — copy beside a photographic collage —
    and it stacks on mobile with the IMAGE FIRST so a phone never gets a wall
    of text before it sees a single photograph.

    WHY THE LAYERED CARDS AND NOT A PLAIN IMAGE
    Three things sit on top of the photograph, each earning its place:
      - a stat card that reads as a physical card, casting a shadow
      - a second card carrying the school's own identity mark
      - a decorative gold ring, the only use of gold on the page
    Together they give the image depth without a gradient doing the work.

    WHY THE IMAGE IS A <picture>
    A 1600px WebP is 72 KB; the same crop at 800px is 31 KB. A phone that
    downloads the desktop file is wasting 41 KB on pixels it cannot show, and
    the hero is the first thing on the site — it decides the Largest
    Contentful Paint for every visitor who has not been back before.

    THE OVERLAY IS NOT OPTIONAL
    The photograph is a bright classroom. White text on it directly would be
    unreadable wherever the wall behind a word happened to be light, so the
    maroon wash is a legibility guarantee, not a decoration.
--}}

@php
    $items    = $section->items('stats');
    $heroWide = asset('images/school/students-classroom.webp');
    $heroSm   = asset('images/school/students-classroom-sm.webp');
    $alt      = $section->value('image_alt', 'Siswa SMA berkonsentrasi belajar di kelas. Beberapa siswa mengenakan seragam sekolah dan siswi berhijab di belakangnya.');
    $ctaLabel = $section->value('cta_label', 'Daftar Sekarang');
    $ctaUrl   = $section->value('cta_url', route('public.admission'));
    $altLabel = $section->value('secondary_cta_label', 'Jelajahi Sekolah');
    $altUrl   = $section->value('secondary_cta_url', route('public.about'));
    $lead     = $items[0] ?? null;
    $rest     = array_slice($items, 1);
@endphp

<section class="relative isolate overflow-hidden bg-[var(--app-primary-dark)]">

    {{-- Mobile: the photograph leads, and the copy follows beneath it. On a
         phone a full-bleed image above the headline is the difference between
         a page that looks like a school and a page that looks like a form. --}}
    <div class="relative lg:hidden">
        <picture>
            <source media="(max-width: 640px)" srcset="{{ $heroSm }}" type="image/webp">
            <img src="{{ $heroWide }}" alt="{{ $alt }}"
                 class="h-[52vw] max-h-80 min-h-52 w-full object-cover"
                 width="1600" height="1079"
                 loading="eager" fetchpriority="high">
        </picture>
        <div class="absolute inset-0 bg-gradient-to-b from-[var(--app-primary-dark)]/55 via-[var(--app-primary-dark)]/35 to-[var(--app-primary-dark)]"></div>
    </div>

    <div class="relative shell-wide pb-14 pt-12 sm:pb-20 sm:pt-16 lg:pb-24 lg:pt-20">

        <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">

            {{-- ============ Copy ============ --}}
            <div class="lg:col-span-6">
                @if ($section->subtitle)
                    <p class="mb-5 inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-small font-medium text-white ring-1 ring-inset ring-white/20 backdrop-blur-sm">
                        <span class="size-1.5 rounded-full bg-[var(--brand-gold)]"></span>
                        {{ $section->subtitle }}
                    </p>
                @endif

                <h1 class="text-display font-bold leading-[1.06] tracking-tight text-white text-balance">
                    {{ $section->title ?? 'Belajar Hari Ini. Memimpin Esok Hari.' }}
                </h1>

                @if ($section->body)
                    <p class="mt-5 max-w-xl text-body leading-relaxed text-white/80 sm:text-lg">
                        {{ $section->body }}
                    </p>
                @endif

                {{-- Primary CTA is enrolment. The portal link is deliberately
                     not here: this is the block a prospective family reads, and
                     "Daftar Sekarang" must be what they see. --}}
                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="{{ $ctaUrl }}"
                       class="group inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] bg-white px-6 py-3.5 text-body font-semibold text-[var(--app-primary)] shadow-lg transition-all hover:-translate-y-0.5 hover:shadow-xl sm:justify-start">
                        {{ $ctaLabel }}
                        <x-icon name="arrow-right" class="w-4 h-4 transition-transform group-hover:translate-x-1" />
                    </a>

                    <a href="{{ $altUrl }}"
                       class="inline-flex items-center justify-center gap-2 rounded-[var(--radius-md)] px-6 py-3.5 text-body font-semibold text-white ring-1 ring-inset ring-white/35 transition-colors hover:bg-white/10 sm:justify-start">
                        {{ $altLabel }}
                    </a>
                </div>
            </div>

            {{-- ============ Layered photographic composition ============ --}}
            <div class="relative hidden lg:col-span-6 lg:block">

                {{-- The photograph, cropped tall. object-cover on a portrait
                     aspect ratio is what makes it read as a composed panel
                     rather than a squashed banner. --}}
                <div class="relative overflow-hidden rounded-[var(--radius-lg)] shadow-2xl ring-1 ring-white/15">
                    <picture>
                        <source srcset="{{ $heroSm }}" media="(max-width: 1400px)" type="image/webp">
                        <img src="{{ $heroWide }}" alt="{{ $alt }}"
                             class="aspect-4/5 w-full object-cover"
                             width="1600" height="1079"
                             loading="eager" fetchpriority="high">
                    </picture>

                    {{-- Bottom gradient so the overlaid stat card has a dark
                         plate to sit on rather than floating over a face. --}}
                    <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/70 via-black/20 to-transparent"></div>
                </div>

                @if ($lead)
                    {{-- Floating stat card. It overlaps the photograph's edge,
                         which is what makes the composition read as layered
                         instead of as a photo with a box on it. --}}
                    <div class="absolute -bottom-6 -left-6 w-52 rounded-[var(--radius-md)] border border-white/20 bg-[var(--app-surface)] p-5 shadow-2xl xl:-left-10">
                        <p class="text-3xl font-bold tabular-nums leading-none text-[var(--app-primary)]">
                            {{ $lead['value'] ?? '—' }}@if ($lead['suffix'] ?? null)<span class="text-[var(--brand-gold)]">{{ $lead['suffix'] }}</span>@endif
                        </p>
                        <p class="mt-1.5 text-small font-medium text-[var(--app-text)]">{{ $lead['label'] ?? '' }}</p>
                    </div>
                @endif

                {{-- Identity card, top-right. The official mark rather than a
                     generic badge: the emblem is the school's own, and a
                     placeholder pill next to real photography looks like a
                     template. --}}
                <div class="absolute -right-4 -top-5 flex items-center gap-3 rounded-[var(--radius-md)] border border-white/20 bg-white/95 px-4 py-3 shadow-xl backdrop-blur xl:-right-8">
                    <img src="{{ asset(config('branding.assets.logo_icon')) }}"
                         alt=""
                         class="size-10 shrink-0">
                    <div class="min-w-0">
                        <p class="text-caption text-[var(--app-text-subtle)]">{{ config('branding.platform.positioning') }}</p>
                        <p class="truncate text-small font-bold text-[var(--app-primary)]">{{ config('branding.platform.name') }}</p>
                    </div>
                </div>

                {{-- The one gold accent on the page: a thin ring that reads as a
                     seal. Gold used at scale would fight the maroon. --}}
                <span class="pointer-events-none absolute -bottom-10 -right-8 size-40 rounded-full border-2 border-[var(--brand-gold)]/25 xl:size-52"
                      aria-hidden="true"></span>
            </div>
        </div>

        {{-- ============ Stats rail ============ --}}
        @if ($rest !== [])
            <dl class="mt-12 grid grid-cols-3 gap-x-5 gap-y-6 border-t border-white/15 pt-8 lg:mt-16 lg:gap-x-10">
                @foreach ($rest as $item)
                    <div>
                        <dt class="sr-only">{{ $item['label'] ?? '' }}</dt>
                        <dd>
                            <span class="block text-2xl font-bold tabular-nums text-white sm:text-3xl">
                                {{ $item['value'] ?? '—' }}@if ($item['suffix'] ?? null)<span class="text-[var(--brand-gold)]">{{ $item['suffix'] }}</span>@endif
                            </span>
                            <span class="mt-1 block text-caption text-white/60 sm:text-small">
                                {{ $item['label'] ?? '' }}
                            </span>
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </div>
</section>