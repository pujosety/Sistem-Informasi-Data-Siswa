<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassroomAnnouncement;
use App\Models\Department;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\EnrollmentService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Editing a class announcement.
 *
 * Three things are being pinned down here, and each has bitten a real school:
 *
 *  1. SCOPE. A Wali Kelas of X IPA 1 must not be able to correct an
 *     announcement in X IPA 2 by editing the id in the URL. The route
 *     middleware only proves the PERMISSION; the policy proves the scope.
 *  2. NO REPARENTING. classroom_id is bound by the route, never by the
 *     payload, so a crafted classroom_id cannot move a notice to another class.
 *  3. NOTIFY ONLY ON REAL CHANGE. Recipients already hold the published text,
 *     so a correction must re-notify — but a no-op save must not spam them.
 */
class AnnouncementEditTest extends TestCase
{
    use RefreshDatabase;

    private SchoolClass $ownClass;

    private SchoolClass $otherClass;

    private User $wali;

    private AcademicYear $year;

    /** @var string[] permissions this file revoked from a role */
    private array $revoked = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        $this->revoked = [];

        $this->year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
            'is_default' => true,
        ]);

        $department = Department::create(['name' => 'IPA', 'code' => 'IPA']);

        $this->ownClass = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 1',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);

        $this->otherClass = SchoolClass::create([
            'academic_year_id' => $this->year->id,
            'department_id' => $department->id,
            'name' => 'X IPA 2',
            'level' => 'X',
            'capacity' => 36,
            'status' => SchoolClass::ACTIVE,
        ]);

        $this->wali = $this->makeUser('wali_kelas');

        HomeroomAssignment::create([
            'user_id' => $this->wali->id,
            'classroom_id' => $this->ownClass->id,
            'academic_year_id' => $this->year->id,
            'started_at' => now()->toDateString(),
            'status' => HomeroomAssignment::ACTIVE,
        ]);
    }

    /**
     * Re-issue the Wali Kelas role minus one permission.
     *
     * revokePermissionTo() is not enough: the grant comes from the ROLE, so
     * removing a direct permission leaves the ability intact. A derived role is
     * the honest way to test "holds everything except this one thing".
     *
     * @param  string  $without  permission to omit
     */
    private function waliWithout(string $without): User
    {
        $source = \Spatie\Permission\Models\Role::findByName('wali_kelas', 'web');

        $role = \Spatie\Permission\Models\Role::create([
            'name' => 'wali_kelas_no_'.str_replace('.', '_', $without),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions(
            $source->permissions->pluck('name')->reject(fn ($p) => $p === $without)->all()
        );

        $limited = $this->makeUser('wali_kelas');
        $limited->removeRole('wali_kelas');
        $limited->assignRole($role->name);

        \Illuminate\Support\Facades\Gate::forgetCachedPermissions();
        $limited = $limited->fresh();

        // Sanity: the derived role must differ ONLY by the omitted permission.
        $this->assertFalse($limited->can($without));
        $this->assertTrue($limited->can('classroom.announcement.create'));

        return $limited;
    }

    private function announcementFor(SchoolClass $classroom, array $overrides = []): ClassroomAnnouncement
    {
        return ClassroomAnnouncement::create(array_merge([
            'classroom_id' => $classroom->id,
            'academic_year_id' => $classroom->academic_year_id,
            'title' => 'Judul salah ketik',
            'body' => 'Isi pengumuman awal.',
            'audience' => 'both',
            'published_at' => now()->subHour(),
            'created_by' => $this->wali->id,
        ], $overrides));
    }

    /**
     * Put back anything a test revoked from a role.
     *
     * RefreshDatabase restores the schema, NOT Spatie's in-process permission
     * cache, so a revokePermissionTo() outlives the test that made it and
     * silently closes every later test that depends on the same grant — in a
     * file that never mentions it.
     */
    protected function tearDown(): void
    {
        $role = \Spatie\Permission\Models\Role::findByName('wali_kelas');

        foreach ($this->revoked as $permission) {
            if ($role && ! $role->permissions->contains($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        parent::tearDown();
    }

    private function editUrl(SchoolClass $classroom, ClassroomAnnouncement $a): string
    {
        return "/akademik/kelas/{$classroom->id}/pengumuman/{$a->id}/ubah";
    }

    private function updateUrl(SchoolClass $classroom, ClassroomAnnouncement $a): string
    {
        return "/akademik/kelas/{$classroom->id}/pengumuman/{$a->id}";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Judul yang sudah dikoreksi',
            'body' => 'Isi pengumuman yang sudah dikoreksi.',
            'audience' => 'both',
            'publish' => 1,
        ], $overrides);
    }

    // ----------------------------------------------------------------- SCOPE

    public function test_wali_kelas_can_open_the_edit_form_for_their_own_class(): void
    {
        $a = $this->announcementFor($this->ownClass);

        $this->actingAs($this->wali)
            ->get($this->editUrl($this->ownClass, $a))
            ->assertOk()
            ->assertSee('Ubah Pengumuman');
    }

    public function test_wali_kelas_cannot_edit_another_classrooms_announcement(): void
    {
        $foreign = $this->announcementFor($this->otherClass);

        // The permission is held — only the ASSIGNMENT is missing. This is the
        // case the route middleware alone cannot catch.
        $this->assertTrue($this->wali->can('classroom.announcement.update'));

        $this->actingAs($this->wali)
            ->get($this->editUrl($this->otherClass, $foreign))
            ->assertForbidden();

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->otherClass, $foreign), $this->payload())
            ->assertForbidden();

        // And the foreign record is genuinely untouched.
        $this->assertSame('Judul salah ketik', $foreign->fresh()->title);
    }

    public function test_wali_kelas_cannot_reach_a_foreign_announcement_through_their_own_class_url(): void
    {
        // Mismatched pair: the classroom in the URL is theirs, the announcement
        // is not. Must 404, never render another class's text.
        $foreign = $this->announcementFor($this->otherClass);

        $this->actingAs($this->wali)
            ->get($this->editUrl($this->ownClass, $foreign))
            ->assertNotFound();

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $foreign), $this->payload())
            ->assertNotFound();
    }

    public function test_a_user_without_the_update_permission_is_refused_even_in_their_own_class(): void
    {
        $a = $this->announcementFor($this->ownClass);

        // Strip ONLY the update grant, keeping view and the homeroom
        // Revoked from the ROLE, not from the user. The catalogue grants
        // permissions to roles, and a user holding a role still has everything
        // that role has — so revoking on the instance leaves `can()` true and
        // the assertion below fails for a reason that has nothing to do with
        // the feature.
        $role = \Spatie\Permission\Models\Role::findByName('wali_kelas');
        $role->revokePermissionTo('classroom.announcement.update');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->wali = $this->wali->fresh();
        $this->revoked[] = 'classroom.announcement.update';

        $this->assertFalse($this->wali->can('classroom.announcement.update'));
        $this->assertTrue($this->wali->can('classroom.announcement.create'));

        $this->actingAs($this->wali)
            ->get($this->editUrl($this->ownClass, $a))
            ->assertForbidden();
    }

    // --------------------------------------------------------- NO REPARENTING

    public function test_edit_cannot_move_an_announcement_to_another_class(): void
    {
        $a = $this->announcementFor($this->ownClass);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload([
                'classroom_id' => $this->otherClass->id,
                'academic_year_id' => $this->year->id,
            ]))
            ->assertRedirect(route('academic.announcements.index', $this->ownClass));

        $a->refresh();

        $this->assertSame($this->ownClass->id, $a->classroom_id);
        $this->assertSame($this->ownClass->academic_year_id, $a->academic_year_id);
    }

    public function test_a_published_announcement_keeps_its_original_publication_time(): void
    {
        $publishedAt = now()->subDays(3)->startOfSecond();
        $a = $this->announcementFor($this->ownClass, ['published_at' => $publishedAt]);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload())
            ->assertRedirect();

        // Re-publishing must not reset the clock: readers rely on this to see
        // how stale a notice is.
        $this->assertTrue($publishedAt->equalTo($a->fresh()->published_at));
    }

    // -------------------------------------------------------------- NOTIFYING

    public function test_correcting_a_published_announcement_notifies_again(): void
    {
        Notification::fake();

        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $this->ownClass, $this->wali, 'test');

        $a = $this->announcementFor($this->ownClass);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload())
            ->assertRedirect();

        Notification::assertSentTo(
            $student->user,
            \App\Notifications\SidaNotification::class,
            fn ($n) => $n->type === NotificationService::CLASS_ANNOUNCEMENT
                && ($n->context['corrected'] ?? false) === true,
        );
    }

    public function test_resaving_without_a_real_change_does_not_notify_again(): void
    {
        Notification::fake();

        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $this->ownClass, $this->wali, 'test');

        $a = $this->announcementFor($this->ownClass);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload([
                'title' => $a->title,
                'body' => $a->body,
            ]))
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_editing_a_draft_does_not_notify_until_it_is_published(): void
    {
        Notification::fake();

        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $this->ownClass, $this->wali, 'test');

        $a = $this->announcementFor($this->ownClass, ['published_at' => null]);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload(['publish' => null]))
            ->assertRedirect();

        $this->assertNull($a->fresh()->published_at);
        Notification::assertNothingSent();
    }

    public function test_publishing_a_draft_by_editing_notifies_audience(): void
    {
        Notification::fake();

        $student = Student::factory()->create();
        app(EnrollmentService::class)->assign($student, $this->ownClass, $this->wali, 'test');

        $a = $this->announcementFor($this->ownClass, ['published_at' => null]);

        $this->actingAs($this->wali)
            ->put($this->updateUrl($this->ownClass, $a), $this->payload())
            ->assertRedirect();

        $this->assertNotNull($a->fresh()->published_at);
        Notification::assertSentTo(
            $student->user,
            \App\Notifications\SidaNotification::class,
            fn ($n) => ($n->context['corrected'] ?? false) === false,
        );
    }

    // -------------------------------------------------------------- VALIDATION

    public function test_failed_validation_redisplays_what_was_typed(): void
    {
        $a = $this->announcementFor($this->ownClass);

        // The payload omits `title` and `body` and sends an audience that is
        // not in the allowed set, so BOTH custom messages have to be on the
        // redisplayed form. The previous version sent audience='both' — which
        // is valid — and then asserted the audience message appeared, so it
        // was asking for an error the controller was right not to raise.
        $this->actingAs($this->wali)
            ->from($this->editUrl($this->ownClass, $a))
            ->put($this->updateUrl($this->ownClass, $a), ['audience' => 'aliens'])
            ->assertRedirect($this->editUrl($this->ownClass, $a))
            ->assertSessionHasErrors(['title', 'body', 'audience']);

        $this->actingAs($this->wali)
            ->get($this->editUrl($this->ownClass, $a))
            ->assertOk()
            ->assertSee('Audiens tidak valid.');
    }

    public function test_invalid_audience_is_rejected(): void
    {
        $a = $this->announcementFor($this->ownClass);

        $this->actingAs($this->wali)
            ->from($this->editUrl($this->ownClass, $a))
            ->put($this->updateUrl($this->ownClass, $a), $this->payload(['audience' => 'everyone']))
            ->assertSessionHasErrors('audience');

        $this->assertSame('both', $a->fresh()->audience);
    }

    // ----------------------------------------------------------------- DELETE

    public function test_delete_requires_the_delete_permission(): void
    {
        $a = $this->announcementFor($this->ownClass);

        // Role, not instance — see the note in the update test above.
        $role = \Spatie\Permission\Models\Role::findByName('wali_kelas');
        $role->revokePermissionTo('classroom.announcement.delete');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->wali = $this->wali->fresh();
        $this->revoked[] = 'classroom.announcement.delete';

        $this->actingAs($this->wali)
            ->delete($this->updateUrl($this->ownClass, $a))
            ->assertForbidden();

        $this->assertDatabaseHas('classroom_announcements', ['id' => $a->id]);
    }
}
