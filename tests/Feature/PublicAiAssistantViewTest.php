<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicAiAssistantViewTest extends TestCase
{
    public function test_public_layout_exposes_an_accessible_lala_trigger_and_dialog(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="lyfla-ai"'));
        $this->assertStringContainsString('aria-controls="lyfla-ai-panel"', $html);
        $this->assertStringContainsString('id="lyfla-ai-panel"', $html);
        $this->assertStringContainsString('aria-labelledby="lyfla-ai-title"', $html);
        $this->assertStringContainsString('id="lyfla-ai-title"', $html);
        $this->assertStringContainsString('role="status"', $html);
    }
}
