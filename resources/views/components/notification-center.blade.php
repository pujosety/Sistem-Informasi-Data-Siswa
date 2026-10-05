@props([
    'items' => [],
    'unreadCount' => 0,
])

{{-- The notification drawer (brief §32).

     IT IS A DRAWER, NOT A DROPdown, because a notification is a thing you READ,
     and a dropdown that closes on the first click outside makes reading four
     notifications impossible.

     EVERY ITEM DEEP-LINKS (brief §32) and the link is the whole row, not a
     title inside it. A notification you cannot act on is a message, and the
     most common version of this feature is a list of unopenable messages.

     The "mark all read" control is a real form, not a JS call, so it works
     without JavaScript and so a failure is visible in the response rather than
     silently optimistically removing the badges. --}}

<div x-data="{ open: false }"
     @keydown.escape.window="if (open) open = false"
     class="relative">

    {{-- Trigger --}}
    <button type="button" @click="open = !open"
            :aria-expanded="open ? 'true' : 'false'"
            class="relative grid place-items-center w-9 h-9 rounded-[var(--radius-md)]
                   text-[var(--app-text-muted)] hover:bg-[var(--app-surface-muted)]
                   hover:text-[var(--app-text)] transition-colors"
            aria-label="Notifikasi ({{ $unreadCount }} belum dibaca)">
        <x-icon name="bell" class="w-[18px] h-[18px]" />

        @if ($unreadCount > 0)
            <span class="absolute top-1 right-1 min-w-[16px] h-4 px-1 grid place-items-center
                         rounded-full bg-[var(--app-danger)] text-white text-[10px] font-bold tabular-nums">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    {{-- Backdrop closes it, and Escape is bound above. --}}
    <div x-show="open" x-cloak>
        <div x-show="open" x-transition.opacity.duration.150ms
             class="fixed inset-0 z-40 bg-black/20" @click="open = false" aria-hidden="true"></div>

        <aside x-show="open"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="translate-y-2 opacity-0"
               x-transition:enter-end="translate-y-0 opacity-100"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100"
               x-transition:leave-end="opacity-0"
               class="absolute right-0 top-full mt-2 z-50 w-[min(22rem,calc(100vw-2rem))]
                      bg-[var(--app-surface)] border border-[var(--app-border)]
                      rounded-[var(--radius-md)] shadow-xl overflow-hidden
                      flex flex-col max-h-[70vh]"
               role="dialog"
               aria-label="Notifikasi">

            <header class="flex items-center justify-between gap-2 px-4 h-11 border-b border-[var(--app-border)] shrink-0">
                <h2 class="text-small font-semibold text-[var(--app-text)]">Notifikasi</h2>

                @if ($unreadCount > 0 && Route::has('notifications.readAll'))
                    <form method="POST" action="{{ route('notifications.readAll') }}">
                        @csrf
                        <button type="submit" class="text-caption text-[var(--app-primary)] hover:underline">
                            Tandai semua dibaca
                        </button>
                    </form>
                @endif
            </header>

            {{-- Categorised by group (brief §32). The category is part of the
                 content, not a filter UI that has to be learned: at a glance
                 "Academic 3 · Document 1" already answers what happened. --}}
            @forelse ($items as $item)
                @php $unread = ! ($item['read_at'] ?? $item['read'] ?? null); @endphp

                <a href="{{ $item['url'] ?? '#' }}"
                   class="flex items-start gap-3 px-4 py-3 border-b border-[var(--app-border)]
                          hover:bg-[var(--app-surface-muted)] transition-colors last:border-b-0
                          {{ $unread ? 'bg-[var(--app-primary-soft)]/40' : '' }}">

                    <span class="shrink-0 mt-0.5 w-7 h-7 grid place-items-center rounded-[var(--radius-sm)]
                                 {{ $unread ? 'bg-[var(--app-primary-soft)] text-[var(--app-primary)]' : 'bg-[var(--app-surface-muted)] text-[var(--app-text-subtle)]' }}">
                        <x-icon :name="$item['icon'] ?? 'bell'" class="w-4 h-4" />
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-baseline gap-2">
                            <span class="text-small font-medium text-[var(--app-text)] truncate">{{ $item['title'] }}</span>
                            @if ($unread)
                                <span class="size-1.5 rounded-full bg-[var(--app-primary)] shrink-0"
                                      title="Belum dibaca" aria-label="Belum dibaca"></span>
                            @endif
                        </span>

                        @if ($item['body'] ?? false)
                            <span class="block mt-0.5 text-caption text-[var(--app-text-muted)] line-clamp-2">
                                {{ $item['body'] }}
                            </span>
                        @endif

                        <span class="mt-1 flex items-center gap-2 text-caption text-[var(--app-text-subtle)]">
                            @if ($item['group'] ?? false)
                                <span>{{ $item['group'] }}</span><span aria-hidden="true">·</span>
                            @endif
                            <time>{{ $item['time'] ?? '' }}</time>
                        </span>
                    </span>
                </a>
            @empty
                <div class="px-4 py-10">
                    <x-empty-state compact icon="bell"
                                   title="Tidak ada notifikasi"
                                   description="Pemberitahuan penting akan muncul di sini." />
                </div>
            @endforelse
        </aside>
    </div>
</div>