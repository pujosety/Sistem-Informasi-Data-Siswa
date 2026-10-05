@props([
    'widgets' => [],
    'storageKey' => 'sida.dashboard.layout',
    'columns' => 3,
])

{{-- A dashboard grid whose widgets can be reordered, hidden and restored
     (brief §8), built on Alpine and HTML5 drag events rather than a library.

     WHY LAYOUT LIVES IN localStorage AND NOT THE DATABASE: a preference for
     "put attendance first" is not worth a table, a migration, and an endpoint
     that only ever answers for one user. If it is lost, the default order
     returns — which is a small price for not having a per-user layout table
     attached to every dashboard forever. Swap this for an AJAX PUT the moment
     anyone asks to sync a layout between two machines.

     NOT DRAGGABLE ON MOBILE (brief §8): HTML5 drag-and-drop does not fire on
     touch, so a pointer-driven implementation would either not work or fight
     the scroll gesture. Below `md` the grid is a plain vertical stack and the
     ordering is simply not editable there.

     The order is applied through a CSS `order` value rather than by moving DOM
     nodes, so a reorder never touches the elements themselves — that matters
     because these widgets hold charts and, on some pages, an open drawer's
     state. --}}

@php
    // The layout is NOT read from a cookie: it lives in localStorage, on the
    // client, because it is a per-browser display preference and not something
    // the server should be told about on every page render.
@endphp

<div x-data="dashboardGrid(@js($storageKey), @js(array_map(fn ($w) => [
    'id' => $w['id'],
    'size' => $w['size'] ?? 'sm',
], $widgets)))"
     class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-{{ $columns }}">

    @foreach ($widgets as $widget)
        @php
            $id = $widget['id'];
            $body = $widget['slot'] ?? null;
        @endphp

        {{-- A widget with nothing to show is omitted entirely rather than
             rendered as an empty panel: a bordered box with no content reads as
             something failed to load, which is a different problem. --}}
        @if ($body !== null && trim((string) $body) !== '')
            @php
                $span = match ($widget['size'] ?? 'sm') {
                    'wide' => 'md:col-span-2',
                    'full' => 'md:col-span-2 lg:col-span-'.$columns,
                    default => '',
                };
            @endphp

            {{-- Deliberately NOT a .surface: most slots are already an
                 <x-card>, and a card inside a card is the nested-panel look the
                 brief rules out. This wrapper only positions the drag handle
                 and the hide button. --}}
            <section data-widget-id="{{ $id }}"
                     class="{{ $span }} relative group"
                     :class="hidden ? 'hidden' : ''"
                     draggable="true"
                     @dragstart="drag($event, '{{ $id }}')"
                     @dragover.prevent
                     @drop.prevent="drop($event, '{{ $id }}')"
                     @dragend="dragId = null">

                <div class="absolute top-2 right-2 z-10 opacity-0 group-hover:opacity-100 focus-within:opacity-100
                            transition-opacity flex items-center gap-1">
                    <button type="button" @click="toggle('{{ $id }}')"
                            class="w-7 h-7 grid place-items-center rounded-[var(--radius-sm)]
                                   bg-[var(--app-surface)] text-[var(--app-text-subtle)]
                                   hover:text-[var(--app-text)] transition-colors"
                            :aria-label="hiddenWidgets.includes('{{ $id }}') ? 'Tampilkan widget' : 'Sembunyikan widget'"
                            :title="hiddenWidgets.includes('{{ $id }}') ? 'Tampilkan' : 'Sembunyikan'">
                        <x-icon name="eye" class="w-4 h-4" />
                    </button>

                    <span class="hidden md:grid place-items-center w-7 h-7 cursor-grab
                                 bg-[var(--app-surface)] rounded-[var(--radius-sm)]
                                 text-[var(--app-text-subtle)]"
                          title="Geser untuk mengurutkan"
                          aria-hidden="true">
                        <x-icon name="grip-vertical" class="w-4 h-4" />
                    </span>
                </div>

                <div x-bind:style="`order: ${orderOf('{{ $id }}')}`">
                    {!! $body !!}
                </div>
            </section>
        @endif
    @endforeach
</div>

{{-- The controller lives here rather than in app.js because it is only ever
     needed by this component, and a dashboard-only concern sitting in a shared
     file is how shared files become unreadable. --}}
@once
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('dashboardGrid', (storageKey, widgets) => ({
        order: [],
        hiddenWidgets: [],
        dragId: null,

        init() {
            try {
                const saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
                this.order = saved.order || widgets.map(w => w.id);
                this.hiddenWidgets = saved.hidden || [];
            } catch {
                this.order = widgets.map(w => w.id);
            }
        },

        persist() {
            localStorage.setItem(storageKey, JSON.stringify({
                order: this.order,
                hidden: this.hiddenWidgets,
            }));
        },

        orderOf(id) {
            const i = this.order.indexOf(id);
            return i === -1 ? 999 : i;
        },

        toggle(id) {
            this.hiddenWidgets = this.hiddenWidgets.includes(id)
                ? this.hiddenWidgets.filter(w => w !== id)
                : [...this.hiddenWidgets, id];
            this.persist();
        },

        drag(event, id) {
            this.dragId = id;
            event.dataTransfer.effectAllowed = 'move';
            // Firefox requires data to be set for a drag to start at all.
            event.dataTransfer.setData('text/plain', id);
        },

        drop(event, targetId) {
            if (!this.dragId || this.dragId === targetId) return;

            const from = this.order.indexOf(this.dragId);
            const to = this.order.indexOf(targetId);

            this.order.splice(to, 0, this.order.splice(from, 1)[0]);
            this.persist();
            this.dragId = null;
        },
    }));
});
</script>
@endonce