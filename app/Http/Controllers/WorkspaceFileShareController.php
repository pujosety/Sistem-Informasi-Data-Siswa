<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceFileShareService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceFileShareController extends Controller
{
    public function __construct(private readonly WorkspaceFileShareService $shares) {}

    public function manage(Request $request, string $fileId): View
    {
        return view('workspace.shares.manage', [
            'file' => $this->shares->ownedFile($request->user(), $fileId),
            'shares' => $this->shares->sharesFor($request->user(), $fileId),
            'maxExpiryDays' => WorkspaceFileShareService::MAX_EXPIRY_DAYS,
        ]);
    }

    public function grant(Request $request, string $fileId): RedirectResponse
    {
        // Resolve owner scope before validating recipient details.
        $this->shares->ownedFile($request->user(), $fileId);
        $data = $request->validate([
            'recipient_email' => ['required', 'string', 'email', 'max:255'],
            'expires_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:now', 'before_or_equal:'.now()->addDays(WorkspaceFileShareService::MAX_EXPIRY_DAYS)->format('Y-m-d H:i:s')],
            'owner_id' => ['prohibited'], 'recipient_id' => ['prohibited'], 'school_id' => ['prohibited'],
            'workspace_file_id' => ['prohibited'], 'path' => ['prohibited'], 'disk' => ['prohibited'],
            'rights' => ['prohibited'], 'permission' => ['prohibited'],
        ]);
        $expiry = empty($data['expires_at']) ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data['expires_at'], config('app.timezone'));
        $this->shares->grant($request->user(), $fileId, $data['recipient_email'], $expiry);

        return redirect()->route('workspace.shares.manage', $fileId)->with('success', 'Izin baca diberikan.');
    }

    public function revoke(Request $request, string $fileId, string $shareId): RedirectResponse
    {
        $this->shares->revoke($request->user(), $fileId, $shareId);

        return redirect()->route('workspace.drive.index')->with('success', 'Izin baca dicabut.');
    }

    public function received(Request $request): View
    {
        return view('workspace.shares.received', ['shares' => $this->shares->received($request->user())]);
    }

    public function download(Request $request, string $shareId): StreamedResponse
    {
        [$file, $version, $stream] = $this->shares->openDownload($request->user(), $shareId);

        return response()->streamDownload(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }, $file->name, [
            'Content-Type' => $version->mime_type,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
