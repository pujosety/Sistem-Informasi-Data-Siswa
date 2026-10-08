@props([
    'src',
    'alt' => '',
    'class' => '',
    'loading' => 'lazy',
    'decoding' => 'async',
    'width' => null,
    'height' => null,
    'sizes' => '100vw',
    'fetchpriority' => null,
])

@php
    $source = (string) $src;
    $sourcePath = parse_url($source, PHP_URL_PATH) ?: $source;
    $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
    $basePath = $extension ? substr($sourcePath, 0, -strlen($extension) - 1) : $sourcePath;
    $isStaticRaster = in_array($extension, ['jpg', 'jpeg', 'png'], true);
    $widePath = $isStaticRaster ? $basePath.'.webp' : null;
    $smallPath = $isStaticRaster ? $basePath.'-sm.webp' : null;
    $wideUrl = $widePath && is_file(public_path(ltrim($widePath, '/')))
        ? asset(ltrim($widePath, '/'))
        : null;
    $smallUrl = $smallPath && is_file(public_path(ltrim($smallPath, '/')))
        ? asset(ltrim($smallPath, '/'))
        : null;
    $displayUrl = $wideUrl ?: $source;
@endphp

<picture>
    @if ($smallUrl)
        <source media="(max-width: 640px)" srcset="{{ $smallUrl }}" sizes="{{ $sizes }}" type="image/webp">
    @endif
    @if ($wideUrl)
        <source srcset="{{ $wideUrl }}" sizes="{{ $sizes }}" type="image/webp">
    @endif
    <img
        src="{{ $displayUrl }}"
        alt="{{ $alt }}"
        class="{{ $class }}"
        loading="{{ $loading }}"
        decoding="{{ $decoding }}"
        @if ($width) width="{{ $width }}" @endif
        @if ($height) height="{{ $height }}" @endif
        @if ($fetchpriority) fetchpriority="{{ $fetchpriority }}" @endif
    >
</picture>
