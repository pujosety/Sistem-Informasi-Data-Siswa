@props(['section'])

{{--
    SCHOOL JOURNEY — brief §12.

    Five steps on a horizontal line. The connector is drawn by the ITEM, not by
    a wrapper, because a wrapper's line cannot know it is the last step — and
    the line running past the final dot is exactly what makes a timeline look
    unfinished.

    On mobile it becomes a vertical rail, which is not a fallback: a five-across
    horizontal timeline at 390px gives each step 60px and no readable label.
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-bg)]">
        <div class="shell-wide py-20 sm:py-24">
            <div class="mx-auto max-w-2xl text-center">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Perjalanan Bersama Kami' }}
                </h2>
                @if ($section->body)
                    <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                @endif
            </div>

            <ol class="mt-14 space-y-8 lg:flex lg:items-start lg:space-y-0">
                @foreach ($items as $item)
                    @php $isLast = $loop->last; @endphp

                    <li class="relative flex gap-5 lg:flex-1 lg:flex-col lg:items-center lg:text-center">
                        {{-- Connector: stops at the last step. --}}
                        @unless ($isLast)
                            <span class="absolute left-[19px] top-11 h-[calc(100%-1rem)] w-px bg-[var(--app-border)] lg:left-1/2 lg:top-[19px] lg:h-px lg:w-[calc(100%-1rem)] lg:-translate-x-1/2"
                                  aria-hidden="true"></span>
                        @endunless

                        <span class="relative z-10 grid size-10 shrink-0 place-items-center rounded-full bg-[var(--app-primary)] text-white ring-4 ring-[var(--app-bg)] lg:ring-4">
                            <x-icon :name="$item['icon'] ?? 'circle'" class="w-[18px] h-[18px]" />
                        </span>

                        <div class="lg:mt-4">
                            <p class="text-small font-semibold text-[var(--app-text)]">{{ $item['title'] ?? '' }}</p>
                            @if ($item['body'] ?? null)
                                <p class="mt-1 text-caption leading-relaxed text-[var(--app-text-muted)]">{{ $item['body'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
