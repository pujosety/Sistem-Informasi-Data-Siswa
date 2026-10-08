<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicResponsiveImagesTest extends TestCase
{
    public function test_static_landing_photo_renders_responsive_webp_sources(): void
    {
        $html = view('components.public.responsive-image', [
            'src' => asset('images/schools/smpn4metro/landing/profile-community.jpg'),
            'alt' => 'Profil sekolah',
            'class' => 'photo',
            'width' => 1672,
            'height' => 941,
            'sizes' => '(min-width: 1024px) 50vw, 100vw',
        ])->render();

        $this->assertStringContainsString('profile-community.webp', $html);
        $this->assertStringContainsString('profile-community-sm.webp', $html);
        $this->assertStringContainsString('type="image/webp"', $html);
        $this->assertStringContainsString('width="1672"', $html);
        $this->assertStringContainsString('height="941"', $html);
    }

    public function test_missing_variants_keep_the_original_source(): void
    {
        $html = view('components.public.responsive-image', [
            'src' => asset('images/does-not-exist/original.jpg'),
            'alt' => 'Fallback',
        ])->render();

        $this->assertStringContainsString('images/does-not-exist/original.jpg', $html);
        $this->assertStringNotContainsString('type="image/webp"', $html);
    }
}
