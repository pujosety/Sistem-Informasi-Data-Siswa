@props([
    'name' => null,
    'value' => null,
    'placeholder' => 'Cari…',
    'label' => null,
    'method' => 'GET',
    'autofocus' => false,
    'inline' => false,
])

{{-- A search box.

     `inline` decides whether this renders its OWN form or just the input.

     WHY THAT IS A PROP AND NOT ALWAYS-A-FORM: `x-filter-bar` is itself a GET
     form, and putting a form inside a form is exactly the nesting bug that
     made a student's document upload vanish while reporting success. A
     browser silently discards the inner <form> tags, so the search input
     would post to the filter's action with the filter's fields — and the
     search would appear not to work at all.

     So: standalone, it is a complete GET form and works alone. Inside a filter
     bar, it renders only the input and inherits that form's action, which is
     the correct behaviour and the reason the prop exists.

     Posting the query as `q` keeps one convention across the app, and
     `autocomplete="off"` matters on a list of people: a browser that
     autofills a student's name into a "find a student" box turns a filter
     into a data leak on a shared machine. --}}

@php
    $id = $attributes->get('id') ?: ($name ? 'q-'.preg_replace('/[^a-z0-9]+/i', '-', $name) : 'q-search');
    $fieldName = $name ?? 'q';
    $current = old($fieldName, $value);
@endphp

@if ($inline)
    <div class="relative w-full">
        <label class="sr-only" for="{{ $id }}">{{ $label ?? $placeholder }}</label>
        <x-icon name="search"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-[var(--app-text-subtle)]" />
        <input type="search"
               id="{{ $id }}"
               name="{{ $fieldName }}"
               value="{{ $current }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               @if ($autofocus) autofocus @endif
               class="field pl-10 w-full" />
    </div>
@else
<form method="{{ $method }}"
      action="{{ $attributes->get('action') ?? request()->fullUrl() }}"
      role="search"
      class="w-full"
      {{ $attributes->only('class') }}>

    @if ($method === 'POST')
        @csrf
    @endif

    <div class="relative">
        <label class="sr-only" for="{{ $id }}">{{ $label ?? $placeholder }}</label>

        <x-icon name="search"
                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-[var(--app-text-subtle)]" />

        <input type="search"
               id="{{ $id }}"
               name="{{ $fieldName }}"
               value="{{ $current }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               @if ($autofocus) autofocus @endif
               class="field pl-10 w-full" />

        {{-- A control to CLEAR is what makes a filter usable: after a long
             search the only way back is to select the text and delete it, which
             on touch means a long-press. --}}
        @if ($current)
            <a href="{{ request()->url() }}"
               class="absolute right-1 top-1/2 -translate-y-1/2 h-8 px-2.5 grid place-items-center
                      rounded-[var(--radius-sm)] text-caption text-[var(--app-text-muted)]
                      hover:text-[var(--app-text)] hover:bg-[var(--app-surface-muted)] transition-colors">
                Hapus
            </a>
        @endif
    </div>
</form>
@endif