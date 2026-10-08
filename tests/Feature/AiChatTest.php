<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_continue_another_users_conversation(): void
    {
        $school = School::query()->where('is_default', true)->firstOrFail();
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $conversation = AiConversation::withoutGlobalScopes()->create([
            'school_id' => $school->id,
            'user_id' => $owner->id,
            'session_id' => 'owner-session',
            'title' => 'Percakapan pemilik',
            'last_message_at' => now(),
        ]);

        $this->actingAs($intruder)
            ->postJson(route('ai.chat'), [
                'message' => 'Halo',
                'conversation_id' => $conversation->id,
            ])
            ->assertNotFound();
    }

    public function test_a_user_can_continue_their_own_conversation(): void
    {
        $school = School::query()->where('is_default', true)->firstOrFail();
        $owner = User::factory()->create();
        $conversation = AiConversation::withoutGlobalScopes()->create([
            'school_id' => $school->id,
            'user_id' => $owner->id,
            'session_id' => 'owner-session',
            'title' => 'Percakapan pemilik',
            'last_message_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->postJson(route('ai.chat'), [
                'message' => 'Halo',
                'conversation_id' => $conversation->id,
            ])
            ->assertOk();

        $this->assertSame($conversation->id, $response->json('conversation_id'));
        $this->assertStringContainsString('Lala', (string) $response->json('answer'));
    }
}
