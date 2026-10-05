@props(['section'])

{{--
    FAQ — brief §14.

    A native <details> element, not a JS accordion. It answers the question with
    no script, it is keyboard-operable for free, and it survives a failed asset
    load. A hand-rolled accordion on a page whose job is to convert visitors is
    the wrong place to depend on JavaScript.

    The first row is open by default, because a FAQ where every answer is
    collapsed reads as "there might be nothing here".
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="bg-[var(--app-bg)]">
        <div class="shell-wide py-20 sm:py-24">

            <div class="grid gap-10 lg:grid-cols-12 lg:gap-16">
                <div class="lg:col-span-4">
                    @if ($section->subtitle)
                        <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                    @endif
                    <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                        {{ $section->title ?? 'Pertanyaan yang Sering Diajukan' }}
                    </h2>
                    @if ($section->body)
                        <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                    @endif
                </div>

                <div class="lg:col-span-8">
                    <div class="divide-y divide-[var(--app-border)] overflow-hidden rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)]">
                        @foreach ($items as $index => $item)
                            <details class="group" @if ($index === 0) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left text-body font-medium text-[var(--app-text)] transition-colors hover:bg-[var(--app-surface-muted)] sm:px-6">
                                    <span>{{ $item['question'] ?? '' }}</span>
                                    <x-icon name="chevron-down"
                                            class="w-4 h-4 shrink-0 text-[var(--app-text-subtle)] transition-transform group-open:rotate-180" />
                                </summary>
                                <div class="px-5 pb-5 text-small leading-relaxed text-[var(--app-text-muted)] sm:px-6">
                                    {{ $item['answer'] ?? '' }}
                                </div>
                            </details>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
