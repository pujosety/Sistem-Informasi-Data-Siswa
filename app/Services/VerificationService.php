<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Registration;
use Illuminate\Support\Facades\DB;

class VerificationService
{
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
