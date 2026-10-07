@props([
    // 'lockup' = icon + wordmark, 'icon' = emblem only.
    'variant' => 'lockup',
    // Sizing. Accepts a Tailwind height utility ('h-8', 'h-10') and derives a
    // matching width for the square icon. A raw CSS length is deliberately not
    // accepted: '40px' is not a Tailwind class, so it emitted an invalid class
    // that then collided with the real one.
    'height' => null,
    'alt' => null,
    'src' => null,
    // Inline SVG would be crisper, but the official asset is a PNG and the
    // mark must not be redrawn, so the source is used as authored.
])

@php
    $isIcon = $variant === 'icon';

    $brandSettings = $brand ?? [];
    $configuredIcon = data_get($brandSettings, 'icon');
    $configuredLogo = data_get($brandSettings, 'logo');
    $usingLogoAsIconFallback = $isIcon && blank($configuredIcon) && filled($configuredLogo);
    $configuredSource = $src ?: ($isIcon
        ? ($configuredIcon ?: $configuredLogo)
        : $configuredLogo);

    $brandName = data_get($brandSettings, 'shortName')
        ?: config('branding.platform.name');
    $brandExpansion = config('branding.platform.expansion');

    $altText = $alt ?? ($isIcon
        ? $brandName
        : $brandName.' — '.$brandExpansion);

    $heightClass = $height ?? ($isIcon ? 'h-8 w-8' : 'h-9 w-auto');

    // Prefer the administrator-managed asset. Fall back to the repository
    // default only when no uploaded logo/icon exists.
    $src = filled($configuredSource) ? $configuredSource : match ($variant) {
        'icon'       => asset(config('branding.assets.logo_icon')),
        'horizontal' => asset(config('branding.assets.logo_horizontal')),
        default      => asset(config('branding.assets.logo')),
    };

    // attributes already carries any class passed by the caller, so it is
    // appended once — merge() would add the default a second time and produce
    // duplicates like "w-8 h-8 w-8 h-8".
    $callerClass = trim($attributes->get('class', ''));
    $finalClass = trim($callerClass !== '' ? $callerClass.' '.$heightClass : $heightClass);
    if ($usingLogoAsIconFallback) {
        $finalClass .= ' object-contain';
    }
@endphp

{{--
    The official artwork, referenced by one path per variant. The artwork is
    never redrawn in CSS and never approximated with an icon font: the emblem
    combines a graduation cap with a flowing S-ribbon, which has no icon-font
    equivalent.
--}}
<img
    src="{{ $src }}"
    alt="{{ $altText }}"
    @if ($isIcon)
        {{-- No loading="lazy" on the emblem. It is a 32px image that sits in
             the first frame of every authenticated page, and a lazy image
             inside the scrollable sidebar is unreliable: the browser has no
             reason to decode something it believes is off-screen. --}}
        width="32" height="32" decoding="async"
    @else
        width="998" height="568" loading="lazy" decoding="async"
    @endif
    @if ($finalClass) class="{{ $finalClass }}" @endif
    {{ $attributes->except('class') }}
>
