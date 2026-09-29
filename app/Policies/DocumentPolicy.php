<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Who may read a student document.
 *
 * WHY THIS MOVED OUT OF THE CONTROLLER
 *
 * The ownership check used to live in DocumentFileController::authorizeDocument(),
 * and it worked — eleven tests cover it. But authorization living in a
 * controller only protects the code paths that go through that controller. The
 * moment a second surface exists — a signed download link, a console command,
 * a JSON API for a mobile app, a support tool for a lost document — none of it
 * inherits the check, and the failure is silent because the endpoint simply
 * works.
 *
 * That is the shape of an IDOR: not a broken check, but a check that is present
 * in one place and absent everywhere else. A policy is the one place a new
 * caller is obliged to look, which is the whole argument for having it.
 *
 * WHY STAFF BYPASS WITH A ROLE CHECK
 *
 * `document.verify` and `document.download` exist and are granted to kesiswaan
 * and verifikator. Checking the permission rather than the role name means a
 * school that redefines a role is honoured immediately, and it means the
 * catalogue is the single answer to "who may see documents" instead of a list
 * repeated in a controller and a seeder.
 */
class DocumentPolicy
{
    use HandlesAuthorization;

    /**
     * Staff who review documents as part of their job.
     *
     * Deliberately not `super_admin`: Gate::before already short-circuits that
     * role, and listing it here would imply it is required.
     */
    private const REVIEW_PERMISSIONS = ['document.verify', 'document.download'];

    /*
     | Roles that could read any document before this policy existed.
     |
     | The controller bypassed by ROLE — `admin` and `kesiswaan` — while
     | `kesiswaan` holds neither document.verify nor document.download in the
     | catalogue. A permission-only policy would therefore take document
     | access away from every kesiswaan on day one, which is a behaviour change
     | disguised as a refactor.
     |
     | So the previous grant is preserved exactly, and the gap is recorded
     | rather than papered over: if kesiswaan is meant to review documents, the
     | catalogue is missing `document.download` from its grants, and that is a
     | decision about roles rather than about this file.
     */
    private const LEGACY_REVIEW_ROLES = ['admin', 'kesiswaan'];

    public function viewAny(User $user): bool
    {
        return $this->isReviewer($user);
    }

    /**
     * Read the file itself — the inline view and the download.
     *
     * The same rule for both. A student can always download their own document;
     * there is no reason to let someone see it but not save it.
     */
    public function view(User $user, Document $document): bool
    {
        if ($this->isReviewer($user)) {
            return true;
        }

        return $this->belongsToStudent($user, $document);
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Accept or reject a document during verification.
     *
     * Separate from `view` on purpose: reviewing a file and seeing it are
     * different powers, and an operator who can read registrations does not
     * thereby gain the right to approve them.
     */
    public function verify(User $user, Document $document): bool
    {
        return $user->can('document.verify');
    }

    public function review(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->can('document.verify');
    }

    private function isReviewer(User $user): bool
    {
        foreach (self::REVIEW_PERMISSIONS as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        // Preserves the pre-policy behaviour exactly. See LEGACY_REVIEW_ROLES.
        return $user->hasAnyRole(self::LEGACY_REVIEW_ROLES);
    }

    /**
     * Is this document attached to a registration belonging to this user?
     *
     * A EXISTS rather than a join-and-fetch: the question is whether the
     * document is theirs, not what it contains, so nothing about the file is
     * read to answer it.
     */
    private function belongsToStudent(User $user, Document $document): bool
    {
        return Student::query()
            ->where('user_id', $user->id)
            ->whereHas('registration', fn ($q) => $q->where('registrations.id', $document->registration_id))
            ->exists();
    }
}
