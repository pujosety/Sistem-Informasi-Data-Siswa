<?php
/**
 * PWA icon generator.
 *
 * Run:  php tools/generate-icons.php
 * Output: public/icons/*.png  + public/favicon.ico
 *
 * Rendering notes: everything is drawn OPAQUE. An earlier version used
 * imagesavealpha() + a transparent fill, which produced a file that decoded as
 * a black rectangle with alpha in some viewers. A fully opaque canvas is both
 * simpler and correct for every consumer (manifest, iOS home screen, favicon).
 */

$out = __DIR__.'/../public/icons';
if (! is_dir($out) && ! @mkdir($out, 0777, true) && ! is_dir($out)) {
    fwrite(STDERR, "cannot create {$out}\n");
    exit(1);
}

/** Brand ramp, top -> bottom, as [r,g,b] triplets. */
$rampTop = [0x3B, 0x5B, 0xDB];
$rampBottom = [0x1E, 0x2F, 0x6B];

$draw = function (int $size, bool $maskable) use ($rampTop, $rampBottom): string {
    $img = imagecreatetruecolor($size, $size);

    // Opaque vertical gradient background.
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        $c = imagecolorallocate(
            $img,
            (int) round($rampTop[0] + ($rampBottom[0] - $rampTop[0]) * $t),
            (int) round($rampTop[1] + ($rampBottom[1] - $rampTop[1]) * $t),
            (int) round($rampTop[2] + ($rampBottom[2] - $rampTop[2]) * $t)
        );
        imageline($img, 0, $y, $size - 1, $y, $c);
    }

    // Maskable art must sit inside the central 80% safe zone.
    $scale = $maskable ? 0.58 : 0.74;
    $u = $size * $scale / 100;               // layout unit
    $cx = (int) round($size / 2);
    $cy = (int) round($size / 2);

    $white = imagecolorallocate($img, 0xFF, 0xFF, 0xFF);
    $tint  = imagecolorallocate($img, 0xDB, 0xE4, 0xFF);

    // ---- Mortarboard: wide flat diamond --------------------------------
    $capHalfW = (int) round($u * 26);
    $capHalfH = (int) round($u * 8.5);
    $capY     = (int) round($cy - $u * 8);

    imagefilledpolygon($img, [
        $cx,            $capY - $capHalfH,       // top
        $cx + $capHalfW, $capY,                   // right
        $cx,            $capY + $capHalfH,       // bottom
        $cx - $capHalfW, $capY,                   // left
    ], $white);

    // ---- Board body under the cap, aligned to the cap's underside ------
    $bodyW    = (int) round($capHalfW * 1.30);
    $bodyTop  = $capY + (int) round($capHalfH * 0.35);
    $bodyH    = (int) round($u * 15);
    $notch    = (int) round($bodyW * 0.34);     // U-shaped dip for the cap edge

    // Draw the body as a rectangle, then re-cut the top edge into a U.
    imagefilledrectangle($img, $cx - (int) ($bodyW / 2), $bodyTop, $cx + (int) ($bodyW / 2), $bodyTop + $bodyH, $tint);
    imagefilledrectangle(
        $img,
        $cx - $notch,
        $bodyTop,
        $cx + $notch,
        $bodyTop + (int) round($u * 7),
        imagecolorallocate($img, $rampTop[0], $rampTop[1], $rampTop[2])
    );

    // ---- Tassel: cord + bead, hung from the cap's right corner ---------
    $tasselX = $cx + $capHalfW;
    imagesetthickness($img, max(2, (int) round($size / 96)));
    imageline($img, $tasselX, $capY, $tasselX, $bodyTop + (int) round($u * 11), $white);

    $bead = max(3, (int) round($u * 3.2));
    imagefilledellipse(
        $img,
        $tasselX,
        $bodyTop + (int) round($u * 12.5),
        $bead,
        $bead,
        $white
    );

    ob_start();
    imagepng($img, null, 9);
    $png = (string) ob_get_clean();
    imagedestroy($img);

    return $png;
};

$targets = [
    'icon-192.png'            => [192, false],
    'icon-512.png'            => [512, false],
    'icon-maskable-512.png'   => [512, true],
    'apple-touch-icon.png'    => [180, false],
    'icon-96.png'             => [96,  false],
];

foreach ($targets as $name => [$size, $maskable]) {
    $path = $out.'/'.$name;
    file_put_contents($path, $draw($size, $maskable));
    printf("wrote %-26s %4dpx  %6d bytes\n", $name, $size, filesize($path));
}

// Browsers request /favicon.ico at the root; PNG data is accepted by all modern ones.
if (is_file($out.'/icon-96.png')) {
    copy($out.'/icon-96.png', __DIR__.'/../public/favicon.ico');
    printf("wrote %-26s %6d bytes\n", 'favicon.ico', filesize(__DIR__.'/../public/favicon.ico'));
}

echo "done\n";
