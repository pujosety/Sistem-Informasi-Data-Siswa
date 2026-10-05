@props([
    'items' => [],
])

{{-- A vertical timeline (brief §29).

     Used for verification history, a student's academic history, the document
     lifecycle, registration progress and the activity log — so the markup has
     to survive all five, which means the ITEM SHAPE is passed in rather than
     assumed. Each entry accepts: title, description, timestamp, tone (for the
     dot), icon, and a slot for anything richer.

     The connector is drawn by the item itself rather than by a wrapper, because
     a wrapper's line cannot know whether it is the last entry — and the one
     before last is exactly the case that looks broken. --}}

<ol {{ $attributes->merge(['class' => 'relative']) }}>
    @foreach ($items as $item)
        @php
            $tone = $item['tone'] ?? 'neutral';
            $dotClass = match ($tone) {
                'success' => 'bg-[var(--app-success)]',
                'warning' => 'bg-[var(--app-warning)]',
                'danger'  => 'bg-[var(--app-danger)]',
                'primary' => 'bg-[var(--app-primary)]',
                default   => 'bg-[var(--app-text-subtle)]',
            };
            $isLast = $loop->last;
        @endphp

        <li class="relative pl-8 {{ $isLast ? 'pb-0' : 'pb-5' }}">
            {{-- Connector: sits behind the dot and stops at the last item. --}}
            @unless ($isLast)
                <span class="absolute left-[7px] top-5 bottom-0 w-px bg-[var(--app-border)]"
                      aria-hidden="true"></span>
            @endunless

            {{-- Dot --}}
            <span class="absolute left-0 top-1 w-[15px] h-[15px] rounded-full {{ $dotClass }}
                         ring-4 ring-[var(--app-surface)]"
                  aria-hidden="true">
                @if ($item['icon'] ?? false)
                    <x-icon :name="$item['icon']" class="w-full h-full p-[1px] text-white" />
                @endif
            </span>

            <div class="min-w-0">
                <div class="flex flex-wrap items-baseline gap-x-2">
                    @if ($item['title'] ?? false)
                        <p class="font-semibold text-[var(--app-text)]">{{ $item['title'] }}</p>
                    @endif
                    @if ($item['timestamp'] ?? false)
                        <time class="text-caption text-[var(--app-text-subtle)]">{{ $item['timestamp'] }}</time>
                    @endif
                </div>

                @if ($item['description'] ?? false)
                    <p class="mt-0.5 text-small text-[var(--app-text-muted)]">{{ $item['description'] }}</p>
                @endif

                @if (! empty($item['detail']))
                    <div class="mt-2">{{ $item['detail'] }}</div>
                @endif
            </div>
        </li>
    @endforeach
</ol>