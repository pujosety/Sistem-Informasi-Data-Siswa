<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactMessageAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_admin_can_read_contact_messages(): void
    {
        $message = ContactMessage::create([
            'name' => 'Alya Pratama',
            'email' => 'alya@example.test',
            'topic' => 'PPDB',
            'message' => 'Saya ingin mengetahui jadwal pendaftaran.',
            'status' => ContactMessage::NEW,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->get('/admin/pesan')
            ->assertOk()
            ->assertSee($message->email)
            ->assertSee($message->message);
    }

    public function test_admin_can_update_contact_message_status(): void
    {
        $message = ContactMessage::create([
            'name' => 'Alya Pratama',
            'email' => 'alya@example.test',
            'topic' => 'PPDB',
            'message' => 'Saya ingin mengetahui jadwal pendaftaran.',
            'status' => ContactMessage::NEW,
        ]);

        $this->actingAs($this->makeUser('admin'))
            ->put('/admin/pesan/'.$message->id, ['status' => ContactMessage::RESOLVED])
            ->assertRedirect(route('admin.contact-messages'));

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => ContactMessage::RESOLVED,
        ]);
    }
}
