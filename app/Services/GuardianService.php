<?php

namespace App\Services;

use App\Exceptions\GuardianLinkException;
use App\Models\GuardianRelationship;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guardian linking (Wali Murid as a LOGIN).
 *
 * The parent portal authorizes on `guardian_relationships`, so this table is
 * the only thing that makes a parent's account reachable. Two rules matter and
 * are enforced here rather than in the controller, because the portal is the
 * thing that breaks if they are forgotten:
 *
 *  1. Linking the same account twice for the same relation is refused. A
 *     duplicate row would let one parent be counted twice and would make
 *     "who is the primary guardian" ambiguous.
 *
 *  2. The LAST guardian of a student cannot be unlinked. Removing it silently
 *     strands the child: their account still exists, but the portal is
 *     unreachable and no one can see why. Refusing loudly is the only honest
 *     behaviour — a school must add another guardian first.
 */
class GuardianService
{
    /**
     * Link a parent login to a student.
     *
     * @throws GuardianLinkException when the same account is already linked
     *                               for the same relation.
     */
    public function link(Student $student, User $guardian, string $relation, bool $isPrimary = false): GuardianRelationship
    {
        $relation = $this->normalizeRelation($relation);

        $duplicate = GuardianRelationship::query()
            ->where('student_id', $student->id)
            ->where('guardian_user_id', $guardian->id)
            ->where('relationship', $relation)
            ->exists();

        if ($duplicate) {
            throw GuardianLinkException::duplicate($guardian, $relation);
        }

        return DB::transaction(function () use ($student, $guardian, $relation, $isPrimary) {
            if ($isPrimary) {
                // Only one primary per child: demote any existing one rather
                // than letting two rows both claim to be the contact of record.
                GuardianRelationship::query()
                    ->where('student_id', $student->id)
                    ->update(['is_primary' => false]);
            }

            return GuardianRelationship::create([
                'student_id' => $student->id,
                'guardian_user_id' => $guardian->id,
                'relationship' => $relation,
                'is_primary' => $isPrimary,
                'status' => GuardianRelationship::ACTIVE,
                'linked_via' => 'admin',
                'verified_at' => now(),
            ]);
        });
    }

    /**
     * Remove a link.
     *
     * @throws GuardianLinkException when this is the student's only guardian.
     */
    public function unlink(GuardianRelationship $link): void
    {
        $remaining = GuardianRelationship::query()
            ->where('student_id', $link->student_id)
            ->where('status', GuardianRelationship::ACTIVE)
            ->where('id', '!=', $link->id)
            ->count();

        if ($remaining === 0) {
            throw GuardianLinkException::lastGuardian($link);
        }

        $link->delete();
    }

    /**
     * Student ids this parent account may reach.
     *
     * Read by the portal's authorization, which answers 404 — not 403 — for a
     * child that is not linked, because a 403 confirms the record exists.
     */
    public function childrenFor(int $userId): array
    {
        return GuardianRelationship::query()
            ->where('guardian_user_id', $userId)
            ->where('status', GuardianRelationship::ACTIVE)
            ->pluck('student_id')
            ->all();
    }

    /**
     * Is this account linked to this child right now?
     */
    public function canAccess(int $userId, int $studentId): bool
    {
        return in_array($studentId, $this->childrenFor($userId), true);
    }

    /**
     * Candidate parent accounts that are not linked to this student yet, so
     * the screen can offer a choice instead of a free-text id.
     */
    public function unlinkedAccountsFor(Student $student)
    {
        $linked = GuardianRelationship::query()
            ->where('student_id', $student->id)
            ->pluck('guardian_user_id')
            ->all();

        return User::query()
            ->where('is_active', true)
            ->when($linked !== [], fn ($q) => $q->whereNotIn('id', $linked))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function normalizeRelation(string $relation): string
    {
        $relation = strtolower(trim($relation));

        if (! array_key_exists($relation, GuardianRelationship::RELATIONSHIPS)) {
            throw GuardianLinkException::unknownRelation($relation);
        }

        return $relation;
    }
}
