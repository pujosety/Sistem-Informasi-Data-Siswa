@props([
    'section' => null,
])

{{--
    Landing page block dispatcher.

    WHY A SWITCH AND NOT @include WITH A VARIABLE PATH
    ----------------------------------------------------
    A dynamic `@include($type)` is resolved by the view finder at render time
    and its failure is SILENT: an unresolvable name produces nothing at all, no
    exception, no log line. That is exactly what happened here — five of
    fourteen sections were simply absent from the page while every one of them
    rendered correctly when asked for directly, and the served page was half
    the size of the same view rendered in isolation.

    A missing section on a school website is a bad failure mode to have, so the
    dispatcher now names every block explicitly. An unknown type falls through
    to nothing by design — one mistyped row must not take the homepage down —
    but a KNOWN type can no longer fail to resolve silently.

    WHY NOT `<x-dynamic-component>`: it is the same mechanism with the same
    silent failure, and it would also make the block names a namespace concern.
--}}

@php
    $type = $section?->type;
@endphp

@if ($type === 'hero')
    @include('public.blocks.hero', ['section' => $section])
@elseif ($type === 'trust')
    @include('public.blocks.trust', ['section' => $section])
@elseif ($type === 'about')
    @include('public.blocks.about', ['section' => $section])
@elseif ($type === 'features')
    @include('public.blocks.features', ['section' => $section])
@elseif ($type === 'programs')
    @include('public.blocks.programs', ['section' => $section])
@elseif ($type === 'experience')
    @include('public.blocks.experience', ['section' => $section])
@elseif ($type === 'achievements')
    @include('public.blocks.achievements', ['section' => $section])
@elseif ($type === 'facilities')
    @include('public.blocks.facilities', ['section' => $section])
@elseif ($type === 'testimonials')
    @include('public.blocks.testimonials', ['section' => $section])
@elseif ($type === 'news')
    @include('public.blocks.news', ['section' => $section])
@elseif ($type === 'journey')
    @include('public.blocks.journey', ['section' => $section])
@elseif ($type === 'ppdb')
    @include('public.blocks.ppdb', ['section' => $section])
@elseif ($type === 'alumni')
    @include('public.blocks.alumni', ['section' => $section])
@elseif ($type === 'faq')
    @include('public.blocks.faq', ['section' => $section])
@elseif ($type === 'cta')
    @include('public.blocks.cta', ['section' => $section])
@endif