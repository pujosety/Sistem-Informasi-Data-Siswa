<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentType;
use App\Models\Registration;
use App\Models\Verification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function __construct(private readonly CompletenessService $completeness) {}

    /**
     * Store (or replace) one document for a registration.
     */
    public function upload(Registration $registration, DocumentType $type, \Illuminate\Http\UploadedFile $file): Document
    {
        $directory = sprintf('documents/%d/%d', $registration->student_id, $registration->id);

        $existing = $registration->documents()->where('document_type_id', $type->id)->first();

        if ($existing) {
            Storage::disk('public')->delete($existing->path);
        }

        $path = $file->store($directory, 'public');

        $document = Document::updateOrCreate(
            [
                'registration_id' => $registration->id,
                'document_type_id' => $type->id,
            ],
            [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_kb' => (int) round($file->getSize() / 1024),
                'status' => 'pending',
                'rejection_reason' => null,
                'uploaded_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
            ]
        );

        $this->reopenIfNeeded($registration);
        $this->completeness->refresh($registration);

        return $document;
    }

    public function delete(Document $document): void
    {
        Storage::disk('public')->delete($document->path);
        $document->delete();
    }

    /**
     * Create a document row in `missing` state so the student sees a checklist.
     */
    public function seedPlaceholders(Registration $registration): void
    {
        $types = DocumentType::where('is_active', true)->orderBy('sort_order')->get();

        DB::transaction(function () use ($registration, $types) {
            foreach ($types as $type) {
                Document::firstOrCreate(
                    [
                        'registration_id' => $registration->id,
                        'document_type_id' => $type->id,
                    ],
                    ['status' => 'missing', 'path' => '', 'original_name' => '']
                );
            }
        });
    }

    /** If a rejected doc is replaced, the registration goes back to pending. */
    private function reopenIfNeeded(Registration $registration): void
    {
        $hasRejection = $registration->documents()->where('status', 'rejected')->exists();

        if ($hasRejection && $registration->status === Registration::STATUS_REVISION) {
            $stillBroken = $registration->documents()
                ->where('status', 'rejected')
                ->orWhere('status', 'missing')
                ->exists();

            if (! $stillBroken) {
                $registration->update(['status' => Registration::STATUS_PENDING, 'admin_note' => null]);
            }
        }
    }

    public function recordVerification(Registration $registration, string $action, ?int $adminId, ?Document $document = null, ?string $note = null): Verification
    {
        return Verification::create([
            'registration_id' => $registration->id,
            'document_id' => $document?->id,
            'admin_id' => $adminId,
            'action' => $action,
            'note' => $note,
        ]);
    }
}
