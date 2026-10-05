@props(['section'])

{{--
    FACILITIES — brief §8.

    Photo cards with the detail hidden until hover. On touch there is no hover,
    so the detail is also revealed by focus-within — otherwise the extra
    information is unreachable on exactly the devices a parent might browse on.

    That is the honest version of "hover state with additional information":
    the information is never only on hover.
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-surface)]">
        <div class="shell-wide py-20 sm:py-24">
            <div class="max-w-2xl">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Ruang untuk Belajar dan Berkembang' }}
                </h2>
                @if ($section->body)
                    <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                @endif
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($items as $item)
                    <article class="group relative overflow-hidden rounded-[var(--radius-md)]">
                        @if ($item['image'] ?? null)
                            <img src="{{ $item['image'] }}"
                                 alt="{{ $item['title'] ?? 'Fasilitas sekolah' }}"
                                 class="aspect-4/5 w-full object-cover transition-transform duration-500 group-hover:scale-[1.04]"
                                 loading="lazy">
                        @else
                            <div class="grid aspect-4/5 w-full place-items-center bg-[var(--app-surface-muted)]">
                                <x-icon :name="$item['icon'] ?? 'building'" class="w-12 h-12 text-[var(--app-text-subtle)]" />
                            </div>
                        @endif

                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent"></div>

                        <div class="absolute inset-x-0 bottom-0 p-5">
                            <h3 class="text-body font-semibold text-white">{{ $item['title'] ?? '' }}</h3>

                            @if ($item['body'] ?? null)
                                <p class="mt-1.5 max-h-0 overflow-hidden text-caption leading-relaxed text-white/80 opacity-0 transition-all duration-300
                                          group-hover:max-h-32 group-hover:opacity-100
                                          focus-within:max-h-32 focus-within:opacity-100">
                                    {{ $item['body'] }}
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
