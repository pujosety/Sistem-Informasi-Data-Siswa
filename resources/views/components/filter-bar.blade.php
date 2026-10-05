@props([
    'method' => 'GET',
    'action' => null,
    'reset' => true,
    'sticky' => false,
])

{{-- The filter row that sits above a table.

     It is a real GET form, not a set of inputs. Two reasons that matters, and
     both are about a URL being the thing that matters:

     A filter that lives only in JavaScript state cannot be linked, bookmarked,
     or reloaded, so a filtered table dies the moment it is shared — which for
     an admin answer to a colleague's question is the entire use case.

     And a GET form means the browser restores the values from `old()` after a
     redirect, so validation that bounces does not wipe what was typed. --}}

@php
    $hasFilters = count(array_filter(request()->query(), fn ($v) => $v !== null && $v !== ''));
@endphp

<form method="{{ $method }}"
      action="{{ $action ?? request()->url() }}"
      class="filter-bar {{ $sticky ? 'sticky top-16 z-20' : '' }}">

    <div class="flex flex-wrap items-end gap-3">
        {{ $slot }}

        <div class="flex items-center gap-2 ml-auto">
            @if ($hasFilters && $reset)
                <a href="{{ request()->url() }}" class="btn btn-ghost btn-sm">Reset</a>
            @endif
            <button type="submit" class="btn btn-secondary btn-sm">Terapkan</button>
        </div>
    </div>
</form>