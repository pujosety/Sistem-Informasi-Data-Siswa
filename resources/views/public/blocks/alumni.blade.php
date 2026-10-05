@props(['section'])

{{--
    ALUMNI / OUTCOME — brief §13.

    "Langkah Berikutnya Dimulai dari Sini". Conditional in the truest sense: a
    school that cannot yet show real outcomes should leave this block empty and
    switch it off in the CMS rather than publish invented university names.

    So this block renders NOTHING when there is no data — no heading, no empty
    panel, no "coming soon". A section that admits it has nothing to show is
    worse than a section that is not there.
--}}

@php
    $stats    = $section->items('stats');
    $notables = $section->items('notables');
@endphp

@if ($stats || $notables)
    <section class="bg-[var(--app-bg)]">
        <div class="shell-wide py-20 sm:py-24">
            <div class="mx-auto max-w-2xl text-center">
                @if ($section->subtitle)
                    <p class="text-small font-semibold uppercase tracking-widest text-[var(--app-primary)]">{{ $section->subtitle }}</p>
                @endif
                <h2 class="mt-3 text-h1 font-bold tracking-tight text-[var(--app-text)] text-balance">
                    {{ $section->title ?? 'Langkah Berikutnya Dimulai dari Sini' }}
                </h2>
                @if ($section->body)
                    <p class="mt-4 text-body leading-relaxed text-[var(--app-text-muted)]">{{ $section->body }}</p>
                @endif
            </div>

            @if ($stats)
                <dl class="mx-auto mt-12 grid max-w-3xl grid-cols-2 gap-8 sm:grid-cols-4">
                    @foreach ($stats as $stat)
                        <div class="text-center">
                            <dd class="text-3xl font-bold tabular-nums text-[var(--app-primary)]">{{ $stat['value'] ?? '' }}</dd>
                            <dt class="mt-1.5 text-small text-[var(--app-text-muted)]">{{ $stat['label'] ?? '' }}</dt>
                        </div>
                    @endforeach
                </dl>
            @endif

            @if ($notables)
                <ul class="mx-auto mt-12 grid max-w-4xl gap-4 sm:grid-cols-2">
                    @foreach ($notables as $person)
                        <li class="flex items-center gap-4 rounded-[var(--radius-md)] border border-[var(--app-border)] bg-[var(--app-surface)] p-4">
                            @if ($person['image'] ?? null)
                                <img src="{{ $person['image'] }}" alt="" class="size-12 shrink-0 rounded-full object-cover" loading="lazy">
                            @else
                                <span class="grid size-12 shrink-0 place-items-center rounded-full bg-[var(--app-primary-soft)] text-small font-bold text-[var(--app-primary)]">
                                    {{ strtoupper(mb_substr($person['name'] ?? '?', 0, 1)) }}
                                </span>
                            @endif

                            <div class="min-w-0">
                                <p class="truncate text-small font-semibold text-[var(--app-text)]">{{ $person['name'] ?? '' }}</p>
                                <p class="truncate text-caption text-[var(--app-text-muted)]">{{ $person['detail'] ?? '' }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
@endif
