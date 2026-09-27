<?php

namespace App\Services;

use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Wali Kelas is an ASSIGNMENT scoped to a classroom and academic year, never a
 * permanent role. Replacing a homeroom closes the previous assignment rather
 * than overwriting it, so the history of who looked after which class in which
 * year stays intact.
 */
class HomeroomService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Assign a homeroom teacher, closing any current holder.
     *
     * @return ?User the previous holder, so the caller can phrase the message
     */
    public function assign(
        SchoolClass $classroom,
        User $teacher,
        ?User $actor = null,
        ?string $effectiveDate = null,
        ?string $notes = null,
    ): ?User {
        $current = $this->currentAssignment($classroom);
        $previousUser = $current?->user;

        DB::transaction(function () use ($current, $classroom, $teacher, $effectiveDate, $notes, $actor) {
            if ($current) {
                $current->update([
                    'status' => 'replaced',
                    'ended_at' => $effectiveDate ?: now()->toDateString(),
                ]);
            }

            HomeroomAssignment::create([
                'user_id' => $teacher->id,
                'classroom_id' => $classroom->id,
                'academic_year_id' => $classroom->academic_year_id,
                'started_at' => $effectiveDate ?: now()->toDateString(),
                'status' => HomeroomAssignment::ACTIVE,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);
        });

        $this->audit->log(
            'homeroom.assign',
            $classroom,
            $previousUser
                ? "Wali kelas {$classroom->name} diganti dari {$previousUser->name} menjadi {$teacher->name}"
                : "{$teacher->name} ditetapkan sebagai wali kelas {$classroom->name}",
            ['previous_user_id' => $previousUser?->id, 'new_user_id' => $teacher->id],
        );

        return $previousUser;
    }

    public function currentAssignment(SchoolClass $classroom): ?HomeroomAssignment
    {
        return $classroom->homeroomAssignments()
            ->with('user')
            ->where('status', HomeroomAssignment::ACTIVE)
            ->latest('started_at')
            ->first();
    }

    /** Release a homeroom assignment without deleting its history. */
    public function remove(SchoolClass $classroom, ?User $actor = null, ?string $reason = null): void
    {
        $current = $this->currentAssignment($classroom);

        if (! $current) {
            return;
        }

        $name = $current->user?->name ?? 'Wali kelas';

        $current->update([
            'status' => 'ended',
            'ended_at' => now()->toDateString(),
            'notes' => $reason ?: $current->notes,
        ]);

        $this->audit->log('homeroom.remove', $classroom, "{$name} tidak lagi menjadi wali kelas {$classroom->name}");
    }

    /**
     * Staff users eligible to be homeroom teachers.
     *
     * Guardians are Wali Murid, never Wali Kelas, so they are excluded.
     */
    public function eligibleTeachers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereDoesntHave('guardianRelationships', fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    /** Full assignment history of a classroom. */
    public function history(SchoolClass $classroom)
    {
        return $classroom->homeroomAssignments()
            ->with('user')
            ->orderByDesc('started_at')
            ->get();
    }
}
