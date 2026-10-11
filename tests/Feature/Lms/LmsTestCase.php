<?php

namespace Tests\Feature\Lms;

use App\Models\Module;
use App\Services\ModuleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class LmsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Module::query()->where('key', 'lms')->update(['is_enabled' => true]);
        app(ModuleService::class)->forget();
    }
}
