@props(['section'])

{{--
    FEATURES — brief §4, "Kenapa Memilih Kami?"

    Six benefit cards. The brief is explicit that the copy must stay short, and
    the layout enforces it: the body is clamped to two lines, so a paragraph
    someone pastes in gets cut rather than turning the section into a wall of
    text. `line-clamp` is the honest way to keep an editable CMS field from
    destroying a design — the content is still stored, just not shouted.
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-surface)]">
        <div class="shell-wide py-20 sm:py-24">

            <div class="max-w-2xl">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">
                        {{ $section->subtitle }}
                    </p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Kenapa Memilih Kami?' }}
                </h2>
                @if ($section->body)
                    <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                @endif
            </div>

            {{-- 3 columns, but the first card is widened on the largest
                 breakpoint so the row has a rhythm instead of six identical
                 boxes. Brief §DESKTOP forbids "card grid → card grid → card
                 grid" as the whole page's texture. --}}
            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($items as $item)
                    <article class="group rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-bg)]
                                  p-6 transition-all duration-200 hover:-translate-y-1 hover:border-[var(--app-primary)]/30 hover:shadow-lg">

                        <span @class([
                            'grid size-11 place-items-center rounded-[var(--radius-sm)] transition-colors',
                            'bg-[var(--app-primary-soft)] text-[var(--app-primary)]',
                            'group-hover:bg-[var(--app-primary)] group-hover:text-white',
                        ])>
                            <x-icon :name="$item['icon'] ?? 'sparkles'" class="w-5 h-5" />
                        </span>

                        <h3 class="mt-5 text-h3 font-semibold text-[var(--app-text)]">
                            {{ $item['title'] ?? '' }}
                        </h3>

                        @if ($item['body'] ?? null)
                            <p class="mt-2 text-small leading-relaxed text-[var(--app-text-muted)] line-clamp-3">
                                {{ $item['body'] }}
                            </p>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif