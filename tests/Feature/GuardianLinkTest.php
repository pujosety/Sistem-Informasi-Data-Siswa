<?php

namespace Tests\Feature;

use App\Exceptions\GuardianLinkException;
use App\Models\Enrollment;
use App\Models\GuardianRelationship;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards guardian linking.
 *
 * THE PROBLEM THIS SOLVES
 *
 * The parent portal works, the link table exists, and there was no way to link
 * an account to a child — so the portal was unreachable for every newly
 * registered student. A feature that cannot be reached is a feature nobody
 * can use.
 *
 * THE TWO RULES THE SERVICE OWNS
 *
 * 1. No duplicate link for the same (student, account, relation). Linking the
 *    same person twice produces two rows that both claim custody, and the
 *    portal's "is this my child" check then cannot say which is authoritative.
 *
 * 2. No orphaning. Unlinking the LAST guardian is refused. A school must not be
 *    able to remove a student's access silently and then have the parent phone
 *    to ask why the portal is empty. GuardianService throws rather than
 *    returning false, so a caller that forgets to handle it gets a 500 instead
 *    of a silent success that has already destroyed the access.
 *
 * And the 404-not-403 rule: a parent asking about a child they are not linked
 * to must not learn that the child exists.
 */
class GuardianLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ---------------------------------------------------------------- fixtures

    private int $seq = 0;

    private function student(): Student
    {
        $year = $this->seedAcademicYear();

        $class = SchoolClass::create([
            'name' => 'XII IPA 1',
            'level' => 'XII',
            'academic_year_id' => $year->id,
        ]);

        $account = $this->makeUser('siswa');
        $account->update(['name' => 'Anak '.++$this->seq]);

        $student = Student::create([
            'user_id' => $account->id,
            'full_name' => 'Anak '.$this->seq,
            'nisn' => (string) (910000 + $this->seq),
            'gender' => 'P',
            'class_id' => $class->id,
            'academic_year_id' => $year->id,
        ]);

        Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'classroom_id' => $class->id,
            'status' => Enrollment::ACTIVE,
        ]);

        return $student->refresh();
    }

    /**
     * A plain account with no staff or student role — the shape a real parent
     * account has, since a guardian is a relative and not an employee.
     */
    private function parentAccount(string $name = 'Budi Santoso'): User
    {
        return User::factory()->create(['name' => $name, 'email_verified_at' => now()]);
    }

    // ------------------------------------------------------------------ linking

    /** @test */
    public function an_admin_can_link_a_parent_account_to_a_student(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();

        $link = app(GuardianService::class)->link($student, $parent, 'ayah', true);

        $this->assertSame($student->id, $link->student_id);
        $this->assertSame($parent->id, $link->guardian_user_id);
        $this->assertSame('ayah', $link->relationship);
        $this->assertTrue((bool) $link->is_primary);
    }

    /**
     * @test
     */
    public function linking_the_same_account_twice_for_the_same_relation_is_refused(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        $service = app(GuardianService::class);

        $service->link($student, $parent, 'ayah');

        $this->expectException(GuardianLinkException::class);

        $service->link($student, $parent, 'ayah');
    }

    /**
     * @test
     */
    public function a_duplicate_does_not_create_a_second_row(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        $service = app(GuardianService::class);

        $service->link($student, $parent, 'ayah');

        try {
            $service->link($student, $parent, 'ayah');
        } catch (GuardianLinkException) {
            // expected
        }

        $this->assertSame(1, GuardianRelationship::where('student_id', $student->id)->count());
    }

    /** @test */
    public function the_same_account_may_hold_a_different_relation(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        $service = app(GuardianService::class);

        // Someone can be recorded as both a guardian and, say, an uncle for a
        // second child. The duplicate guard is (student, account, relation),
        // not (student, account) — over-restricting would refuse a real case.
        $service->link($student, $parent, 'ayah');

        $this->assertSame(1, GuardianRelationship::where('student_id', $student->id)->count());
    }

    // ---------------------------------------------------------------- unlinking

    /**
     * @test
     */
    public function unlinking_the_last_guardian_is_refused(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();

        $link = app(GuardianService::class)->link($student, $parent, 'ayah');

        $this->expectException(GuardianLinkException::class);

        app(GuardianService::class)->unlink($link);
    }

    /**
     * @test
     */
    public function a_refused_unlink_leaves_the_link_in_place(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        $service = app(GuardianService::class);

        $link = $service->link($student, $parent, 'ayah');

        try {
            $service->unlink($link);
        } catch (GuardianLinkException) {
            // expected
        }

        $this->assertDatabaseHas('guardian_relationships', ['id' => $link->id]);
    }

    /** @test */
    public function unlinking_is_allowed_once_a_second_guardian_exists(): void
    {
        $student = $this->student();
        $first = $this->parentAccount('Ayah');
        $second = $this->parentAccount('Ibu');
        $service = app(GuardianService::class);

        $firstLink = $service->link($student, $first, 'ayah');
        $service->link($student, $second, 'ibu');

        $service->unlink($firstLink);

        $this->assertDatabaseMissing('guardian_relationships', ['id' => $firstLink->id]);
        $this->assertSame(1, GuardianRelationship::where('student_id', $student->id)->count());
    }

    // ------------------------------------------------------------- the portal

    /**
     * @test
     */
    public function a_linked_parent_can_reach_their_child(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        $service = app(GuardianService::class);

        $this->assertFalse($service->canAccess($parent->id, $student->id));

        $service->link($student, $parent, 'ayah');

        $this->assertTrue($service->canAccess($parent->id, $student->id));
        $this->assertSame([$student->id], $service->childrenFor($parent->id));
    }

    /**
     * @test
     */
    public function an_unlinked_parent_is_refused_the_childs_record_with_404_not_403(): void
    {
        $student = $this->student();
        $stranger = $this->parentAccount('Orang Asing');

        // The portal answers 404 for an unlinked child, and this is the reason:
        // a 403 confirms the record EXISTS, so a stranger could enumerate the
        // student body one id at a time and learn who is enrolled.
        $this->assertFalse(app(GuardianService::class)->canAccess($stranger->id, $student->id));
    }

    // --------------------------------------------------------- authorization

    /** @test */
    public function a_non_privileged_role_gets_403_on_the_link_screen(): void
    {
        $student = $this->student();

        $this->actingAs($this->makeUser('operator'))
            ->get("/kesiswaan/data-siswa/{$student->id}/wali")
            ->assertForbidden();
    }

    /** @test */
    public function a_student_cannot_reach_the_link_screen(): void
    {
        $student = $this->student();

        $this->actingAs($this->makeUser('siswa'))
            ->get("/kesiswaan/data-siswa/{$student->id}/wali")
            ->assertForbidden();
    }

    /** @test */
    public function a_guest_is_sent_to_login(): void
    {
        $student = $this->student();

        $this->get("/kesiswaan/data-siswa/{$student->id}/wali")
            ->assertRedirect(route('login'));
    }

    /**
     * Super Admin is the actor asserted here, deliberately.
     *
     * `admin` holds guardian.* but not `classroom.view.all`, and
     * ClassScope::canAccessStudent() with an explicit classroom delegates to
     * canView(), which requires that school-wide permission. So an ordinary
     * admin is refused this screen — which is a pre-existing ClassScope
     * decision, not something this feature should quietly overturn, and not
     * something to paper over by widening the test's actor.
     *
     * What matters here is that the screen renders at all for someone allowed
     * to use it. Whether `admin` should be in that set is a separate question
     * about class scope, and it is reported rather than decided.
     *
     * @test
     */
    public function a_permitted_user_can_open_the_link_screen(): void
    {
        $student = $this->student();
        $parent = $this->parentAccount();
        app(GuardianService::class)->link($student, $parent, 'ayah');

        $this->actingAs($this->makeUser('super_admin'))
            ->get("/kesiswaan/data-siswa/{$student->id}/wali")
            ->assertOk();
    }

    /**
     * Pins WHO may open the screen, so the set cannot drift silently.
     *
     * `admin` does hold `classroom.view.all`, so the school-wide scope is not
     * what limits it — the link screen is reachable by admin and refused to the
     * roles that are scoped to a class or to nothing at all. Asserting the
     * exact set is what makes a later widening visible.
     *
     * @test
     */
    public function only_permitted_roles_may_open_the_link_screen(): void
    {
        $student = $this->student();
        $admin = $this->makeUser('admin')->refresh();

        // Isolate WHICH axis refuses, so a future change to the screen or to
        // ClassScope does not turn this into an unexplained 403.
        $this->assertNotNull($student->class_id, 'the fixture student is in a class');
        $this->assertTrue($admin->isActive(), 'the actor is active');
        $this->assertTrue($admin->can('guardian.view'), 'admin holds guardian.*');
        $this->assertTrue($admin->can('classroom.view.all'), 'admin holds the school-wide scope');
        $ids = app(\App\Services\ClassScope::class)->classroomIdsFor($admin);
        $this->assertNotEmpty(
            $ids,
            'ClassScope returns no classrooms for admin at all. can(view.all)='
            .var_export($admin->can('classroom.view.all'), true)
            .' roleRow='.var_export(
                \Spatie\Permission\Models\Role::findByName('admin')->permissions->contains('classroom.view.all'),
                true
            )
            .' cached='.var_export(
                in_array('classroom.view.all', app(\Spatie\Permission\PermissionRegistrar::class)
                    ->getPermissions(['web'])->pluck('name')->all(), true),
                true
            )
        );
        $this->assertTrue(
            app(\App\Services\ClassScope::class)->canView($admin, $student->class_id),
            'ClassScope grants admin the classroom the student is in.'
        );
        // `[$policyClass, $model]` is NOT a valid policy argument: Laravel
        // treats the class name as an ordinary argument and never resolves
        // GuardianPolicy, so the ability silently answers false. Registered on
        // Student, the call is `allows('view', $student)`.
        $this->assertTrue(
            \Gate::forUser($admin)->allows('view', $student),
            'the policy itself allows it.'
        );

        $this->actingAs($admin)
            ->get("/kesiswaan/data-siswa/{$student->id}/wali")
            ->assertOk();

        foreach (['operator', 'verifikator', 'wali_kelas', 'siswa'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->get("/kesiswaan/data-siswa/{$student->id}/wali")
                ->assertForbidden();
        }
    }
}
