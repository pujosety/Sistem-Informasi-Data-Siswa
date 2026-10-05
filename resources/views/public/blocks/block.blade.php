@props([
    'section' => null,
])

{{--
    Landing page block dispatcher.

    A CMS block that does not exist in the front end must be SKIPPED, never
    output. The section list is administrator-editable data, so one row with an
    unknown type — a typo, a block from a newer deploy that was rolled back —
    would otherwise blank the school website for every visitor.

    So the switch falls through to nothing, and the page renders its other
    blocks. A missing block is a smaller failure than a missing homepage.
--}}

@php
    $type = $section?->type;
    $component = $type ? 'public.blocks.'.$type : null;
@endphp

@if ($component && \Illuminate\Support\Facades\View::exists($component))
    @include($component, ['section' => $section])
@endif