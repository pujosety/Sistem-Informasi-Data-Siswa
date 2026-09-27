<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves student documents through an authorization check.
 *
 * Documents must NOT be reachable from /storage/... directly — that would let
 * anyone who guesses or harvests a path download another student's KK, KTP and
 * birth certificate. This controller is the only sanctioned read path.
 */
class DocumentFileController extends BaseController
{
    public function __construct(AuditService $audit, \App\Services\CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    public function show(Request $request, Document $document)
    {
        $this->authorizeDocument($request, $document);

        $path = \Illuminate\Support\Facades\Storage::disk('public')->path($document->path);

        if (! is_file($path)) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan.');
        }

        // Only images and PDFs are ever accepted on upload, but re-check before
        // streaming so a mis-stored file can never be served as something else.
        $isImage = in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
        $mime = $isImage ? $document->mime_type : 'application/pdf';

        $this->audit->log('document.viewed', $document, 'Menampilkan berkas '.$document->documentType?->name);

        $response = response()->file($path, [
            'Content-Type' => $mime,
            // Student documents are private: no caching by shared caches.
            'Cache-Control' => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($request->boolean('download') ? 'attachment' : 'inline')
                .'; filename="'.addslashes($document->original_name ?: 'dokumen').'"',
        ]);

        return $response;
    }

    /**
     * Owner, or staff who legitimately need to review the file.
     */
    private function authorizeDocument(Request $request, Document $document): void
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if ($user->hasAnyRole(['admin', 'kesiswaan'])) {
            return;
        }

        $ownsDocument = Student::where('user_id', $user->id)
            ->whereHas('registration', fn ($q) => $q->where('registrations.id', $document->registration_id))
            ->exists();

        abort_if(! $ownsDocument, 403, 'Berkas ini bukan milik Anda.');
    }
}
