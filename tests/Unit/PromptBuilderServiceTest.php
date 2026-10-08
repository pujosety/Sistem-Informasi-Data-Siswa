<?php

namespace Tests\Unit;

use App\Models\School;
use App\Services\AI\PromptBuilderService;
use Tests\TestCase;

class PromptBuilderServiceTest extends TestCase
{
    public function test_lala_prompt_defines_a_warm_natural_school_assistant_voice(): void
    {
        $prompt = (new PromptBuilderService())->system(new School(['name' => 'SMP Negeri 4 Metro']), []);

        $this->assertStringContainsString('Lala', $prompt);
        $this->assertStringContainsString('ramah', mb_strtolower($prompt));
        $this->assertStringContainsString('natural', mb_strtolower($prompt));
        $this->assertStringContainsString('jangan membuka', mb_strtolower($prompt));
    }

    public function test_context_prompt_keeps_reference_data_grounded_without_robotic_lead_in(): void
    {
        $context = (new PromptBuilderService())->context([]);

        $this->assertStringContainsString('DATA SEKOLAH', $context);
        $this->assertStringNotContainsString('Informasi yang tersedia', $context);
    }
}
