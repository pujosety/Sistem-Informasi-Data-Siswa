@props(['section'])

{{--
    STUDENT EXPERIENCE — brief §6, the emotional one.

    "Lebih Banyak Hal untuk Ditemukan" has a specific job: a prospective
    student should be able to picture themselves in one of these frames. So the
    tiles are named after ACTIVITIES, not departments — "Robotik" puts a child in
    a room, "Laboratorium Komputer" does not.

    The mosaic is intentionally uneven. A uniform four-across grid of photos is
    the least interesting layout available and the brief rules it out.
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-primary-dark)]">
        <div class="shell-wide py-20 sm:py-24">
            <div class="max-w-2xl">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--brand-gold)]">{{ $section->subtitle }}</p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-white text-balance">
                    {{ $section->title ?? 'Lebih Banyak Hal untuk Ditemukan' }}
                </h2>
                @if ($section->body)
                    <p class="mt-4 text-body leading-relaxed text-white/70">{{ $section->body }}</p>
                @endif
            </div>

            <div class="mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    @if (filled($item['url'] ?? null))
                        <a href="{{ $item['url'] }}"
                           class="group relative aspect-[4/3] overflow-hidden rounded-[var(--radius-md)]">
                    @else
                        <article class="group relative aspect-[4/3] overflow-hidden rounded-[var(--radius-md)]">
                    @endif

                        @if ($item['image'] ?? null)
                            <img src="{{ $item['image'] }}"
                                 alt="{{ $item['title'] ?? '' }}"
                                 class="absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                                 loading="lazy">
                        @else
                            <div class="absolute inset-0 grid place-items-center bg-white/5">
                                <x-icon :name="$item['icon'] ?? 'sparkles'" class="w-10 h-10 text-white/25" />
                            </div>
                        @endif

                        <div class="absolute inset-0 bg-gradient-to-t from-black/75 to-transparent"></div>

                        <div class="absolute inset-x-0 bottom-0 p-4 lg:p-5">
                            <h3 class="text-body font-semibold text-white">{{ $item['title'] ?? '' }}</h3>
                            @if ($item['body'] ?? null)
                                <p class="mt-1 line-clamp-2 text-caption text-white/70">{{ $item['body'] }}</p>
                            @endif
                        </div>
                    @if (filled($item['url'] ?? null))
                        </a>
                    @else
                        </article>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif
