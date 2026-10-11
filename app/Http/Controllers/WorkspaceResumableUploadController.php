<?php

namespace App\Http\Controllers;

use App\Services\WorkspaceResumableUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WorkspaceResumableUploadController extends Controller
{
    public function __construct(private WorkspaceResumableUploadService $uploads) {}

    public function create(Request $request): JsonResponse
    {
        $this->only($request, ['name', 'size', 'checksum', 'folder']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.$this->uploads->limits()['max_bytes']],
            'checksum' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'folder' => ['nullable', 'uuid'],
        ]);
        $session = $this->uploads->create($request->user(), $data['name'], (int) $data['size'], $data['checksum'], $data['folder'] ?? null);
        return $this->json($this->uploads->present($session), 201);
    }

    public function show(Request $request, string $upload): JsonResponse
    {
        $this->only($request, []);
        return $this->json($this->uploads->status($request->user(), $upload));
    }

    public function chunk(Request $request, string $upload): JsonResponse
    {
        $this->only($request, ['index', 'checksum', 'chunk']);
        $data = $request->validate([
            'index' => ['required', 'integer', 'min:0', 'max:4095'],
            'checksum' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'chunk' => ['required', 'file', 'max:1024'],
        ]);
        return $this->json($this->uploads->chunk($request->user(), $upload, (int) $data['index'], $data['checksum'], $data['chunk']));
    }

    public function finalize(Request $request, string $upload): JsonResponse
    {
        $this->only($request, []);
        return $this->json($this->uploads->finalize($request->user(), $upload));
    }

    public function destroy(Request $request, string $upload): JsonResponse
    {
        $this->only($request, []);
        return $this->json($this->uploads->cancel($request->user(), $upload));
    }

    private function only(Request $request, array $keys): void
    {
        // Explicitly reject owner/school/raw parent IDs, storage paths and MIME.
        if (array_diff(array_keys($request->except(['_token', '_method'])), $keys)) {
            throw ValidationException::withMessages(['upload' => 'Parameter unggahan tidak diizinkan.']);
        }
    }

    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
