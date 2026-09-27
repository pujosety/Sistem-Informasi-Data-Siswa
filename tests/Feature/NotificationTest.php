<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\Student;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The notification centre must actually work: dispatch, store, count unread,
 * read individually, read all. These tests exercise the real
 * DatabaseNotification channel against the real `notifications` table.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        // DocumentService::seedPlaceholders() iterates DocumentType, so the
        // document fixtures are empty without these.
        $this->seedDocumentTypes();
    }

    private function staff(string $role = 'admin'): User
    {
        return $this->makeUser($role);
    }

    private function studentWithRegistration(string $status = Registration::STATUS_PENDING): Student
    {
        $user = User::factory()->create();
        $user->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $user->id]);

        $year = $this->seedAcademicYear();

        // The factory already provisions a draft registration (student_id is
        // unique), so update it rather than inserting a duplicate.
        $registration = $student->registration;
        $registration->update([
            'academic_year_id' => $year->id,
            'status' => $status,
        ]);

        app(\App\Services\DocumentService::class)->seedPlaceholders($registration->refresh());

        return $student->refresh();
    }

    public function test_notification_center_page_loads(): void
    {
        $user = $this->staff('kesiswaan');

        $this->actingAs($user)
            ->get('/notifikasi')
            ->assertOk()
            ->assertSee('Notifikasi', false);
    }

    public function test_registration_submitted_notifies_staff(): void
    {
        Notification::fake();

        $student = $this->studentWithRegistration(Registration::STATUS_DRAFT);
        $admin = $this->staff('admin');

        app(NotificationService::class)->onRegistrationSubmitted($student->registration);

        Notification::assertSentTo(
            $admin,
            \App\Notifications\SidaNotification::class,
            fn ($n) => $n->type === NotificationService::REGISTRATION_SUBMITTED
                && str_contains($n->title, 'Pendaftaran baru')
        );
    }

    public function test_revision_requested_notifies_the_student_not_staff(): void
    {
        Notification::fake();

        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $admin = $this->staff('admin');

        app(NotificationService::class)->onRevisionRequested(
            $student->registration,
            'Ijazah tidak terbaca'
        );

        Notification::assertSentTo(
            $student->user,
            \App\Notifications\SidaNotification::class,
            fn ($n) => $n->type === NotificationService::REVISION_REQUESTED
                && $n->body === 'Ijazah tidak terbaca'
        );
    }

    public function test_notification_is_persisted_and_counted_as_unread(): void
    {
        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $user = $student->user;

        $this->assertSame(0, $user->unreadNotifications()->count());

        app(NotificationService::class)->onRevisionRequested($student->registration, 'Perbaiki ijazah');

        $user->refresh();

        $this->assertSame(1, $user->unreadNotifications()->count());
        $this->assertSame(1, $user->readNotifications()->count() + $user->unreadNotifications()->count());
        $this->assertSame(0, $user->readNotifications()->count());

        $notification = $user->notifications()->first();
        $this->assertSame('Perbaiki ijazah', $notification->data['body']);
        $this->assertNull($notification->read_at);
    }

    public function test_mark_all_as_read_clears_the_unread_badge(): void
    {
        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $user = $student->user;
        $service = app(NotificationService::class);

        $service->onRevisionRequested($student->registration, 'satu');
        $service->onDocumentRejected($student->registration, 'Kartu Keluarga', 'dua');
        $service->onRegistrationVerified($student->registration);

        $this->assertSame(3, $user->unreadNotifications()->count());

        $this->actingAs($user)
            ->post('/notifikasi/baca-semua')
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(3, $user->readNotifications()->count());
    }

    public function test_marking_read_happens_when_a_notification_is_opened(): void
    {
        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $user = $student->user;

        app(NotificationService::class)->onRevisionRequested($student->registration, 'cek');

        $notification = $user->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertNull($notification->read_at);

        $notification->markAsRead();
        $user->refresh();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_approval_flow_notifies_the_student(): void
    {
        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $registration = $student->registration;
        $admin = $this->staff('admin');

        // Make every required document valid so approval can complete.
        $registration->documents()->update([
            'status' => 'valid',
            'path' => 'documents/test.pdf',
            'original_name' => 'test.pdf',
        ]);

        app(VerificationService::class)->approveRegistration($registration, $admin->id, 'Selamat, data Anda lengkap.');

        $registration->refresh();
        $this->assertSame(Registration::STATUS_VERIFIED, $registration->status);
        $this->assertNotNull($registration->verified_at);
        $this->assertSame($admin->id, $registration->verified_by);

        $this->assertSame(1, $student->user->unreadNotifications()->count());
        $this->assertSame(
            NotificationService::REGISTRATION_VERIFIED,
            $student->user->notifications()->first()->data['type']
        );
    }

    public function test_rejecting_a_document_creates_a_student_notification_with_the_reason(): void
    {
        $student = $this->studentWithRegistration(Registration::STATUS_PENDING);
        $registration = $student->registration;
        $admin = $this->staff('admin');

        $document = $registration->documents()->first();
        $this->assertNotNull($document, 'StudentFactory must seed document placeholders.');
        $document->update([
            'status' => 'pending',
            'path' => 'documents/test.pdf',
            'original_name' => 'kk.pdf',
        ]);

        app(VerificationService::class)->rejectDocument(
            $registration,
            $document,
            $admin->id,
            'Foto kartu keluarga tidak terbaca.'
        );

        $notification = $student->user->notifications()->first();

        $this->assertNotNull($notification);
        $this->assertSame(NotificationService::DOCUMENT_REJECTED, $notification->data['type']);
        $this->assertSame('Foto kartu keluarga tidak terbaca.', $notification->data['body']);
    }

    public function test_notification_page_is_closed_to_guests(): void
    {
        $this->get('/notifikasi')->assertRedirect('/login');
        $this->post('/notifikasi/baca-semua')->assertRedirect('/login');
    }
}
