<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;

/**
 * Reading and writing employment records.
 *
 * The important behaviour here is the null. Existing staff are `users` carrying
 * a staff role, and this migration does not backfill: a `kesiswaan` might be a
 * teacher, an office clerk or a vice principal, and only the school knows
 * which. Guessing would write false HR records that later reach a payslip.
 *
 * So "staff member with no HR record" is a normal state during onboarding, and
 * every method here has to be honest about it rather than throwing.
 */
class EmployeeService
{
    /**
     * The employment for a user, or null when they have no HR record.
     */
    public function forUser(?User $user): ?Employee
    {
        if (! $user) {
            return null;
        }

        return Employee::query()
            ->where('user_id', $user->id)
            // A soft-deleted row means the employment was removed rather than
            // ended; a resignation sets the status instead of deleting.
            ->first();
    }

    public function find(int $employeeId): ?Employee
    {
        return Employee::query()->with(['user', 'department'])->find($employeeId);
    }

    /**
     * Everyone currently employed.
     */
    public function current(?Department $department = null)
    {
        return Employee::query()
            ->with(['user', 'department'])
            ->current()
            ->when($department, fn ($q) => $q->where('department_id', $department->id))
            ->orderBy('employee_number')
            ->get();
    }

    /**
     * Create or update an employment for a user.
     *
     * Keyed on user_id, so calling it twice updates the same employment rather
     * than giving one person two rows — which is the failure a nullable
     * user_id invites if the key is left to the caller.
     */
    public function recordFor(User $user, array $attributes = []): Employee
    {
        $employee = Employee::firstOrNew(['user_id' => $user->id]);

        $employee->fill($attributes);
        $employee->user_id = $user->id;
        $employee->save();

        return $employee->refresh();
    }

    /**
     * End an employment without deleting the record.
     *
     * A resignation must not erase the grade history and homeroom assignments
     * the person created — a hard delete would cascade those away. The status
     * and the date are the record; the row stays.
     */
    public function resign(Employee $employee, ?string $reason = null): Employee
    {
        $employee->update([
            'employment_status' => Employee::RESIGNED,
            'resigned_at' => now(),
            'resignation_reason' => $reason,
        ]);

        return $employee->refresh();
    }

    /**
     * Whether this user holds a staff role at all.
     *
     * Separate from having an HR record, because the two diverge: a person can
     * be staff with no record during onboarding, and can have a record with no
     * staff role after being moved to a non-teaching post.
     */
    public function isStaff(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            'super_admin', 'admin', 'kesiswaan', 'operator', 'verifikator',
            'wali_kelas', 'principal', 'vice_principal', 'academic_staff',
            'staff', 'cms_editor', 'cms_publisher', 'lms_teacher', 'counselor',
        ]);
    }
}
