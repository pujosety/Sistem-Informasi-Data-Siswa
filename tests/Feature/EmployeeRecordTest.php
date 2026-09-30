<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\Grade;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EmployeeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards the employment/identity split.
 *
 * THE REGRESSION THIS PREVENTS
 *
 * Duplicating name, email or phone from `users` into `employees`. That is the
 * obvious way to build the table and it is wrong: a teacher who changes their
 * phone number would then have two copies, one of which is stale, and the stale
 * one is what reaches a report card. So these tests assert that identity lives
 * in exactly one place.
 *
 * The second thing guarded is the null. Existing staff are `users` with a role
 * and this migration does not backfill, because a `kesiswaan` might be a
 * teacher, a clerk or a vice principal and only the school knows. That makes
 * "staff with no HR record" a normal onboarding state, not an error — and a
 * service that throws on it would make the HR module unusable on day one.
 */
class EmployeeRecordTest extends TestCase
{
    use RefreshDatabase;

    private EmployeeService $employees;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->employees = app(EmployeeService::class);
    }

    private function staff(string $role = 'kesiswaan'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user->refresh();
    }

    /**
     * @test
     */
    public function test_a_staff_member_can_have_no_hr_record(): void
    {
        $user = $this->staff();

        // The normal state after this migration: staff exist as users, and the
        // HR record is created when someone gets round to it.
        $this->assertTrue($this->employees->isStaff($user));
        $this->assertNull($this->employees->forUser($user));
    }

    /**
     * @test
     */
    public function test_recording_an_employment_twice_updates_rather_than_duplicates(): void
    {
        $user = $this->staff();

        $first = $this->employees->recordFor($user, ['position' => 'Guru Matematika']);
        $second = $this->employees->recordFor($user, ['position' => 'Guru Fisika']);

        // user_id is nullable, so a careless key would give one person two rows
        // and no query could say which is current.
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Employee::where('user_id', $user->id)->count());
        $this->assertSame('Guru Fisika', $second->position);
    }

    /**
     * @test
     */
    public function test_identity_is_not_duplicated_onto_the_employment(): void
    {
        $user = $this->staff('kesiswaan');
        $user->update(['name' => 'Budi Santoso']);

        $employee = $this->employees->recordFor($user, ['position' => 'Guru IPA']);

        // The employment reaches the person through the account. There is no
        // second copy of a name to fall out of step.
        $this->assertNull($employee->name);
        $this->assertSame('Budi Santoso', $employee->displayName());

        $user->update(['name' => 'Budi Santoso, S.Pd.']);

        $this->assertSame(
            'Budi Santoso, S.Pd.',
            $employee->user->fresh()->name,
            'The employment must read identity live from the account, not from a copy.'
        );
    }

    /**
     * @test
     */
    public function test_an_employee_can_exist_without_an_account(): void
    {
        $department = Department::create(['name' => 'Administrasi', 'code' => 'ADM']);

        $employee = Employee::create([
            'department_id' => $department->id,
            'position' => 'Staf Administrative',
            'employment_status' => Employee::ACTIVE,
            'hire_date' => now()->subMonth(),
        ]);

        $this->assertNull($employee->user);
        $this->assertNull($employee->user_id);

        // Rendering must not assume an account exists.
        $this->assertIsString($employee->displayName());
        $this->assertStringContainsString('Staf Administrative', $employee->displayName());
    }

    /**
     * @test
     */
    public function test_leaving_is_not_the_same_as_resigning(): void
    {
        $employee = $this->employees->recordFor($this->staff(), [
            'employment_status' => Employee::ON_LEAVE,
        ]);

        // On leave is not a resignation: the person still holds the job and is
        // simply not available.
        $this->assertTrue($employee->isCurrent());
        $this->assertTrue($this->employees->isStaff($employee->user));
    }

    /**
     * @test
     */
    public function test_a_resignation_keeps_the_record_and_its_history(): void
    {
        $user = $this->staff('wali_kelas');
        $employee = $this->employees->recordFor($user, ['position' => 'Wali Kelas']);

        // A resignation must not erase the teaching history. A hard delete here
        // would cascade through homeroom assignments and grades.
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-07-01',
            'end_date' => '2027-06-30', 'is_active' => true,
        ]);
        $class = SchoolClass::create([
            'academic_year_id' => $year->id, 'name' => 'X IPA 1',
            'code' => 'X-IPA-1', 'level' => 'X', 'status' => SchoolClass::ACTIVE,
        ]);
        HomeroomAssignment::create([
            'user_id' => $user->id, 'classroom_id' => $class->id,
            'academic_year_id' => $year->id, 'started_at' => $year->start_date,
            'ended_at' => $year->end_date, 'status' => 'active',
        ]);

        $resigned = $this->employees->resign($employee, 'Pindah sekolah');

        $this->assertSame(Employee::RESIGNED, $resigned->employment_status);
        $this->assertNotNull($resigned->resigned_at);
        $this->assertFalse($resigned->isCurrent());

        // The row and everything it points at are still there.
        $this->assertNotNull(Employee::find($employee->id));
        $this->assertSame(1, HomeroomAssignment::where('user_id', $user->id)->count());
    }

    /**
     * @test
     */
    public function test_current_listing_excludes_resigned_and_inactive(): void
    {
        $kept = $this->employees->recordFor($this->staff('kesiswaan'), [
            'employment_status' => Employee::ACTIVE,
        ]);
        $onLeave = $this->employees->recordFor($this->staff('operator'), [
            'employment_status' => Employee::ON_LEAVE,
        ]);
        $gone = $this->employees->recordFor($this->staff('verifikator'), [
            'employment_status' => Employee::RESIGNED,
        ]);

        $ids = $this->employees->current()->pluck('id')->all();

        $this->assertContains($kept->id, $ids);
        // Still employed, just not available today.
        $this->assertContains($onLeave->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    /**
     * @test
     */
    public function test_an_expired_contract_is_reported(): void
    {
        $employee = $this->employees->recordFor($this->staff(), [
            'contract_start' => now()->subYears(2),
            'contract_end' => now()->subWeek(),
        ]);

        $this->assertTrue($employee->isContractExpired());

        $current = $this->employees->recordFor($this->staff('admin'), [
            'contract_end' => now()->addYear(),
        ]);

        $this->assertFalse($current->isContractExpired());
    }

    /**
     * @test
     */
    public function test_a_deleted_employment_is_soft_deleted_and_invisible(): void
    {
        $employee = $this->employees->recordFor($this->staff());
        $employee->delete();

        // Removed rather than ended: a resignation sets the status, so a delete
        // means the record itself was mistaken.
        $this->assertNull(Employee::find($employee->id));
        $this->assertNotNull(Employee::withTrashed()->find($employee->id));
        $this->assertNull($this->employees->forUser($employee->user));
    }
}
