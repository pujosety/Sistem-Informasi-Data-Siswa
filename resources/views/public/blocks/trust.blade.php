@props(['section'])

{{--
    TRUST BAR — brief §2.

    A single row of claims, directly under the hero. Deliberately short and
    icon-led: this is reassurance a visitor absorbs in one second while
    deciding whether to keep reading, so any copy longer than three words turns
    a scannable strip into a paragraph they skip.
--}}

@php
    $items = $section->items('items');
@endphp

@if ($items !== [])
    <section class="border-b border-[var(--app-border)] bg-[var(--app-surface)]">
        <div class="shell-wide">
            <ul class="grid grid-cols-2 gap-x-6 gap-y-5 py-7 sm:py-8 lg:grid-cols-5">
                @foreach ($items as $item)
                    <li class="flex items-center gap-3">
                        <span @class([
                            'grid size-9 shrink-0 place-items-center rounded-[var(--radius-sm)]',
                            'bg-[var(--app-primary-soft)] text-[var(--app-primary)]',
                        ])>
                            <x-icon :name="$item['icon'] ?? 'badge-check'" class="w-[18px] h-[18px]" />
                        </span>
                        <span class="text-small font-medium leading-tight text-[var(--app-text)]">
                            {{ $item['label'] ?? '' }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif