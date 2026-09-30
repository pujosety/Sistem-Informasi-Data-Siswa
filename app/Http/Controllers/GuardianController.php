<?php

namespace App\Http\Controllers;

use App\Exceptions\GuardianLinkException;
use App\Models\GuardianRelationship;
use App\Models\Student;
use App\Policies\GuardianPolicy;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\GuardianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Link a parent LOGIN to a student, and unlink it.
 *
 * This is the only way the parent portal becomes reachable at all: every portal
 * page authorizes on GuardianRelationship, so until a link exists a registered
 * parent sees nothing and cannot tell why.
 *
 * The controller owns presentation and the refusal MESSAGES; GuardianService
 * owns the two rules (no duplicate, no orphaning) so they cannot be bypassed by
 * a future caller.
 */
class GuardianController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly GuardianService $guardians,
    ) {
        // The parent's services are INJECTED, never read back off $this.
        // Forwarding `$this->audit` reads a typed property that does not exist
        // yet, and every request fails with "must not be accessed before
        // initialization" — which points at the constructor rather than at the
        // dependency that is actually missing.
        parent::__construct($audit, $completeness);
    }

    /** Existing links plus the accounts that could still be linked. */
    public function index(Request $request, Student $student): View
    {
        $this->authorize('view', $student);

        return view('kesiswaan.guardians.index', [
            'student' => $student->load(['registration', 'enrollments' => fn ($q) => $q->live()]),
            'links' => GuardianRelationship::query()
                ->where('student_id', $student->id)
                ->with('guardianUser')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(),
            'candidates' => $this->guardians->unlinkedAccountsFor($student),
            'relations' => GuardianRelationship::RELATIONSHIPS,
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $this->authorize('link', $student);

        $data = $request->validate([
            'guardian_user_id' => ['required', 'integer', 'exists:users,id'],
            'relationship' => ['required', 'string'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $guardian = \App\Models\User::findOrFail($data['guardian_user_id']);

        try {
            $link = $this->guardians->link(
                $student,
                $guardian,
                $data['relationship'],
                (bool) ($data['is_primary'] ?? false),
            );
        } catch (GuardianLinkException $e) {
            return $this->backWith($e->getMessage(), 'error')->withInput();
        }

        $this->audit->log('guardian.link', $link, "Tautkan akun orang tua {$guardian->name} ke {$student->full_name}", [
            'student_id' => $student->id,
            'guardian_user_id' => $guardian->id,
        ]);

        return $this->backWith("Akun {$guardian->name} kini dapat mengakses portal untuk {$student->full_name}.");
    }

    public function destroy(Request $request, Student $student, GuardianRelationship $guardianRelationship): RedirectResponse
    {
        // The link must belong to the student in the URL. Route-model binding
        // resolves the id but does not check the pairing, so without this a
        // staff member could unlink student A's guardian via student B's URL.
        abort_unless($guardianRelationship->student_id === $student->id, 404);

        $this->authorize('unlink', $student);

        $guardian = $guardianRelationship->guardianUser;

        try {
            $this->guardians->unlink($guardianRelationship);
        } catch (GuardianLinkException $e) {
            return $this->backWith($e->getMessage(), 'error');
        }

        $this->audit->log('guardian.unlink', $student, "Lepas tautan orang tua dari {$student->full_name}", [
            'student_id' => $student->id,
            'guardian_user_id' => $guardianRelationship->guardian_user_id,
        ]);

        return $this->backWith("Tautan untuk {$student->full_name} telah dilepas.");
    }
}
