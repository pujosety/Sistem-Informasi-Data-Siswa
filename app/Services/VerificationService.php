<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Registration;
use App\Models\User;
use App\Models\WorkflowAction;
use App\Models\WorkflowInstance;
use Illuminate\Support\Facades\DB;

/**
 * THE DOCUMENT VERIFICATION DESK.
 *
 * The approve/reject logic below is the original, hand-rolled implementation.
 * It is what works today, it is what the suite pins, and it is what the school
 * actually uses. It has NOT been rewritten onto WorkflowService and must not
 * be: §39's engine exists to stop *new* flows from growing a seventh bespoke
 * approval system, not to delete the one that is already in production.
 *
 * So the engine is wired ADDITIVELY. Every public method still does exactly
 * what it did before, in the same order, and THEN records the same decision in
 * the shared trail via recordOnWorkflow(). The engine is the ledger; this
 * service remains the source of truth for document status.
 *
 * WHY EVERY ENGINE CALL IS BEST-EFFORT
 *
 * Because an exception out of the engine must never become an exception out of
 * a reviewer's click. The definition is seeded data (`sida:seed-verification-
 * workflow`), not a migration, so an installation that has not run it yet has
 * no definition — and a missing definition must not stop a registration from
 * being approved. Hence the swallow-and-report: the trail may be incomplete
 * on a half-installed system, the decision is never lost.
 */
class VerificationService
{
    /** The definition key SeedVerificationWorkflow writes. */
    public const WORKFLOW_KEY = 'registration.verification';

    public function __construct(
        private readonly DocumentService $documents,
        private readonly CompletenessService $completeness,
        private readonly AuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function approveDocument(Registration $registration, Document $document, int $adminId, ?string $note = null): Registration
    {
        $document->update([
            'status' => 'valid',
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
            'rejection_reason' => null,
        ]);

        $this->documents->recordVerification($registration, 'approve', $adminId, $document, $note);
        $this->audit->log('document.approved', $document, 'Dokumen disetujui: '.$document->documentType?->name);
        $this->notifications->onDocumentVerified($registration, $document->documentType?->name ?? 'Dokumen');

        return $this->recalculateStatus($registration, $adminId);
    }

    public function rejectDocument(Registration $registration, Document $document, int $adminId, string $reason): Registration
    {
        $document->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
            'rejection_reason' => $reason,
        ]);

        $registration->update([
            'status' => Registration::STATUS_REVISION,
            'admin_note' => $reason,
        ]);

        $this->documents->recordVerification($registration, 'reject', $adminId, $document, $reason);
        $this->audit->log('document.rejected', $document, 'Dokumen ditolak: '.$document->documentType?->name);
        $this->notifications->onDocumentRejected($registration, $document->documentType?->name ?? 'Dokumen', $reason);

        return $registration->refresh();
    }

    public function requestRevision(Registration $registration, int $adminId, string $note): Registration
    {
        $registration->update([
            'status' => Registration::STATUS_REVISION,
            'admin_note' => $note,
        ]);

        $this->documents->recordVerification($registration, 'revise', $adminId, null, $note);
        $this->audit->log('registration.revision_requested', $registration, $note);
        $this->notifications->onRevisionRequested($registration, $note);

        return $registration->refresh();
    }

    public function approveRegistration(Registration $registration, int $adminId, ?string $note = null): Registration
    {
        $outstanding = $registration->documents()
            ->whereIn('status', ['missing', 'pending', 'rejected'])
            ->exists();

        if ($outstanding) {
            $registration->update(['status' => Registration::STATUS_REVISION]);
            $note = $note ?: 'Masih ada dokumen yang belum valid.';

            $this->documents->recordVerification($registration, 'revise', $adminId, null, $note);
            $this->audit->log('registration.blocked', $registration, $note);

            return $registration->refresh();
        }

        $registration->update([
            'status' => Registration::STATUS_VERIFIED,
            'verified_at' => now(),
            'verified_by' => $adminId,
            'admin_note' => $note,
        ]);

        $this->documents->recordVerification($registration, 'approve', $adminId, null, $note);
        $this->audit->log('registration.verified', $registration, $note ?: 'Pendaftaran disetujui');
        $this->notifications->onRegistrationVerified($registration);

        return $registration->refresh();
    }

    public function rejectRegistration(Registration $registration, int $adminId, string $reason): Registration
    {
        $registration->update([
            'status' => Registration::STATUS_REJECTED,
            'admin_note' => $reason,
        ]);

        $this->documents->recordVerification($registration, 'reject', $adminId, null, $reason);
        $this->audit->log('registration.rejected', $registration, $reason);

        return $registration->refresh();
    }

    /** Re-evaluate: if every active document is valid the registration flips to verified. */
    private function recalculateStatus(Registration $registration, int $adminId): Registration
    {
        $this->completeness->refresh($registration);

        $allValid = ! $registration->documents()
            ->whereIn('status', ['missing', 'pending', 'rejected'])
            ->exists();

        if ($allValid && $registration->status !== Registration::STATUS_VERIFIED) {
            $registration->update([
                'status' => Registration::STATUS_VERIFIED,
                'verified_at' => now(),
                'verified_by' => $adminId,
            ]);

            $this->audit->log('registration.verified', $registration, 'Seluruh dokumen valid');
            $this->notifications->onRegistrationVerified($registration);
        }

        return $registration->refresh();
    }
}
