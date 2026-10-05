@props([
    'nodes' => [],
    'level' => 0,
    'labelKey' => 'name',
])

{{-- A recursive file tree (brief §25).

     Recursion is done by RE-INCLUDING this component rather than by a
     `@foreach` with a manual depth variable, because a tree whose second level
     needs a different treatment is the normal case rather than the exception —
     and a flat loop has no way to say that.

     The 21st.dev reference is a flat array walked with an index and a computed
     depth. That works, but it puts the indentation maths in the template, and
     this version lets a caller override the body per node through the slot. --}}

@php
    // A re-included component must NOT receive the parent's attribute bag. On
    // the recursive call that bag holds an ARRAY of nodes, and merging it hands
    // an array to htmlspecialchars(), which throws. The class is passed
    // explicitly instead — the root takes a caller class, every level below it
    // gets the indent rule.
    $rootClass = $level === 0
        ? trim('space-y-0.5 '.($attributes->get('class') ?? ''))
        : 'mt-0.5 space-y-0.5 ml-4 pl-3 border-l border-[var(--app-border)]';
@endphp

<ul class="{{ $rootClass }}">
    @foreach ($nodes as $node)
        @php
            $name = data_get($node, $labelKey);
            $hasChildren = ! empty($node['children']);
            $url = $node['url'] ?? null;
            $isActive = ($node['active'] ?? false);
        @endphp

        <li x-data="{ open: @js($node['open'] ?? $isActive) }">

            @if ($hasChildren)
                <button type="button"
                        @click="open = !open"
                        :aria-expanded="open ? 'true' : 'false'"
                        class="w-full flex items-center gap-2 px-2.5 py-1.5 rounded-[var(--radius-sm)]
                               text-small text-[var(--app-text)] hover:bg-[var(--app-surface-muted)] transition-colors">
                    <x-icon name="chevron-right"
                            class="w-3.5 h-3.5 shrink-0 text-[var(--app-text-subtle)] transition-transform"
                            ::class="open && 'rotate-90'" />
                    @if ($node['icon'] ?? false)
                        <x-icon :name="$node['icon']" class="w-4 h-4 shrink-0 text-[var(--app-text-muted)]" />
                    @endif
                    <span class="truncate">{{ $name }}</span>
                    @if (($node['count'] ?? null) !== null)
                        <span class="ml-auto text-caption tabular-nums text-[var(--app-text-subtle)]">{{ $node['count'] }}</span>
                    @endif
                </button>

                <div x-show="open" x-collapse>
                    <x-file-tree class="tree-level"
                                :nodes="$node['children']"
                                :level="$level + 1"
                                :label-key="$labelKey" />
                </div>
            @elseif ($url)
                <a href="{{ $url }}"
                   @if ($isActive) aria-current="page" @endif
                   class="flex items-center gap-2 px-2.5 py-1.5 rounded-[var(--radius-sm)] text-small transition-colors
                          {{ $isActive
                                ? 'bg-[var(--app-primary-soft)] text-[var(--app-primary)] font-semibold'
                                : 'text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)] hover:text-[var(--app-text)]' }}">
                    <span class="w-3.5 shrink-0"></span>
                    @if ($node['icon'] ?? false)
                        <x-icon :name="$node['icon']" class="w-4 h-4 shrink-0" />
                    @endif
                    <span class="truncate">{{ $name }}</span>
                    @if (($node['meta'] ?? null) !== null)
                        <span class="ml-auto text-caption text-[var(--app-text-subtle)]">{{ $node['meta'] }}</span>
                    @endif
                </a>
            @else
                <div class="flex items-center gap-2 px-2.5 py-1.5 text-small text-[var(--app-text-muted)]">
                    <span class="w-3.5 shrink-0"></span>
                    @if ($node['icon'] ?? false)
                        <x-icon :name="$node['icon']" class="w-4 h-4 shrink-0 text-[var(--app-text-subtle)]" />
                    @endif
                    <span class="truncate">{{ $name }}</span>
                    @if (($node['meta'] ?? null) !== null)
                        <span class="ml-auto text-caption text-[var(--app-text-subtle)]">{{ $node['meta'] }}</span>
                    @endif
                </div>
            @endif
        </li>
    @endforeach
</ul>