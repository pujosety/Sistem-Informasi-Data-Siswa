<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
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

    public function show(Request $request, Document $document): StreamedResponse
    {
        $this->authorizeDocument($request, $document);

        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        // exists() rather than an is_file() check on a resolved path. The disk
        // may be local or S3 depending on the host, and only the former has a
        // local path to test — a path-based check silently reports "missing"
        // for every real object on a bucket.
        abort_unless($disk->exists($document->path), 404, 'Berkas tidak ditemukan di penyimpanan.');

        // Only images and PDFs are ever accepted on upload, but re-check before
        // streaming so a mis-stored file can never be served as something else.
        $isImage = in_array($document->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
        $mime = $isImage ? $document->mime_type : 'application/pdf';

        $this->audit->log('document.viewed', $document, 'Menampilkan berkas '.$document->documentType?->name);

        $stream = $disk->readStream($document->path);

        if ($stream === false) {
            abort(404, 'Berkas tidak ditemukan di penyimpanan.');
        }

        /*
         * Both signals are checked, and they are not interchangeable.
         *
         * Document::downloadUrl() points at the `documents.download` route,
         * which carries no query string. Testing only $request->boolean('download')
         * therefore saw no flag on that route and answered `inline` for a link
         * whose entire purpose is to save the file — the browser opened the PDF
         * in a tab and the user had to save it by hand.
         *
         * The query parameter is kept because it is what the templates use to
         * force a download on the shared show route, where the route name is
         * the same for both behaviours.
         */
        $wantsDownload = $request->routeIs('documents.download') || $request->boolean('download');

        /*
         * Streamed rather than served with response()->file().
         *
         * response()->file() hands the path to Symfony's BinaryFileResponse,
         * which stats and reads the file from the LOCAL filesystem. On a host
         * where the disk is S3 there is no local file at all, so the response
         * could only ever be a 404. readStream() is driver-agnostic: it reads
         * from local disk or streams from the bucket, so the same controller
         * serves both deployments.
         *
         * The body is streamed rather than buffered for the same reason — a
         * scanned document held in memory is a large allocation inside a
         * function with a hard memory ceiling.
         */
        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            // Student documents are private: no caching by shared caches.
            'Cache-Control' => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($wantsDownload ? 'attachment' : 'inline')
                .'; filename="'.addslashes($document->original_name ?: 'dokumen').'"',
        ]);
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
