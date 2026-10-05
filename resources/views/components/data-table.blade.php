@props([
    'rows' => null,
    'columns' => [],
    'empty' => null,
    'emptyIcon' => 'inbox',
    'emptyTitle' => 'Belum ada data',
    'emptyDescription' => null,
    'emptyAction' => null,
    'caption' => null,
    'selectable' => false,
    'responsive' => true,
])

{{-- The table every list page shares.

     It wraps the EXISTING `.data-table` CSS rather than restating it: 13 tables
     already render that class, and a second definition would be a second place
     for the two to disagree.

     RESPONSIVE BEHAVIOUR (brief §16) — below `md` a row becomes a card. The
     alternative, horizontal scrolling, is worse than it sounds: on a phone a
     scrollable seven-column table means the reader loses sight of the row they
     are on while scrolling to reach its name.

     A column declares itself with `['label' => …, 'primary' => true]`. Primary
     columns are what survives on a card, because the first thing anyone wants
     from a row on a small screen is the name of the thing. --}}

@php
    $rowCount = $rows ? $rows->count() : count($rows ?? []);
    $primaryCount = collect($columns)->where('primary', true)->count();

    // Rows arrive as Eloquent models from a paginator and as plain arrays from
    // a hand-built test fixture or an aggregate. Reading `$row->id` works for
    // one and throws on the other, so the key is resolved by shape.
    $keyOf = fn ($row, string $key) => is_array($row)
        ? ($row[$key] ?? null)
        : data_get($row, $key);
@endphp

@if ($rowCount > 0)
    <div {{ $attributes->merge(['class' => 'w-full']) }}>

        @if ($caption)
            <p class="sr-only">{{ $caption }}</p>
        @endif

        <div class="overflow-x-auto scrollbar-thin">
            <table class="data-table">
                @if ($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif

                <thead>
                    <tr>
                        @if ($selectable)
                            <th scope="col" class="w-10">
                                <input type="checkbox" class="sr-only peer"
                                       x-data
                                       x-ref="all"
                                       @change="$el.closest('table').querySelectorAll('input[type=checkbox][name=\'rows[]\']').forEach(c => c.checked = $el.checked)"
                                       aria-label="Pilih semua baris">
                            </th>
                        @endif

                        @foreach ($columns as $key => $column)
                            @php
                                $label = is_array($column) ? ($column['label'] ?? $key) : $column;
                                $class = is_array($column) ? ($column['class'] ?? '') : '';
                            @endphp
                            <th scope="col" class="{{ $class }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rows as $row)
                        <tr class="transition-colors hover:bg-[var(--app-surface-muted)]/60">
                            @if ($selectable)
                                <td class="w-10">
                                    <input type="checkbox" name="rows[]" value="{{ $keyOf($row, 'id') }}"
                                           class="checkbox"
                                           aria-label="Pilih baris {{ $keyOf($row, 'id') }}">
                                </td>
                            @endif

                            @foreach ($columns as $key => $column)
                                @php
                                    $primary = is_array($column) && ($column['primary'] ?? false);
                                    $hidden  = is_array($column) && ($column['hideOnMobile'] ?? false);
                                    $cellClass = trim(($primary ? 'font-semibold text-[var(--app-text)] ' : '').($hidden ? 'hidden md:table-cell ' : ''));
                                @endphp
                                <td class="{{ $cellClass }}">{{ is_callable($column) ? $column($row) : ($column['value'] ?? $keyOf($row, $key)) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Card layout: the same rows, stacked, for narrow screens. Rendered
             alongside the table and hidden from it rather than replacing it, so
             the markup stays inspectable and both layouts cannot drift. --}}
        @if ($responsive && $primaryCount > 0)
            <ul class="md:hidden space-y-2">
                @foreach ($rows as $row)
                    @php $key = array_search(true, array_map(fn ($c) => is_array($c) && ($c['primary'] ?? false), $columns)); @endphp
                    <li class="surface p-3">
                        <p class="font-semibold text-[var(--app-text)]">
                            {{ $keyOf($row, $key) }}
                        </p>
                        @if (array_key_exists($key, $columns) && is_array($columns[$key]) && ($columns[$key]['secondary'] ?? null))
                            <p class="mt-0.5 text-caption text-[var(--app-text-muted)]">
                                {{ $keyOf($row, $columns[$key]['secondary']) }}
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if (method_exists($rows, 'links'))
            <div class="mt-5">{{ $rows->links() }}</div>
        @endif
    </div>
@else
    <x-empty-state :icon="$emptyIcon"
                   :title="$emptyTitle"
                   :description="$emptyDescription ?? $empty"
                   :action="$emptyAction" />
@endif