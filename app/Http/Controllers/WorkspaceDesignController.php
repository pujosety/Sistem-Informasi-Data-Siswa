<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceFileVersion;
use App\Models\WorkspaceDesignAsset;
use App\Models\WorkspaceDesignProject;
use App\Services\WorkspaceDesignService;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceMalwareScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class WorkspaceDesignController extends Controller
{
    public function __construct(private readonly WorkspaceDesignService $design) {}

    public function index(Request $request)
    {
        return response()->view('workspace.design.index', [
            'projects' => $this->design->owned($request->user())->orderByDesc('updated_at')->paginate(20),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:180']]);
        $schoolId = $this->design->schoolId($request->user());
        $project = DB::transaction(function () use ($request, $data, $schoolId) {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_if($this->design->owned($request->user())->count() >= 100, 422, 'Project limit reached (100).');
            return WorkspaceDesignProject::create([
                'public_id' => (string) Str::uuid(), 'owner_id' => $request->user()->id,
                'school_id' => $schoolId, 'name' => $data['name'], 'revision' => 1,
                'document' => ['width' => 1080, 'height' => 1080, 'pages' => [
                    ['id' => (string) Str::uuid(), 'title' => 'Page 1', 'canvas' => ['version' => '6.9.0', 'objects' => [], 'background' => '#ffffff']],
                ]],
            ]);
        });
        if ($request->expectsJson()) return response()->json($this->payload($project), 201)->header('Cache-Control', 'private, no-store');
        return redirect()->route('workspace.design.editor', $project->public_id);
    }

    public function editor(Request $request, string $project)
    {
        $record = $this->design->project($request->user(), $project);
        return response()->view('workspace.design.editor', ['project' => $record])
            ->header('Cache-Control', 'private, no-store')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
    }

    public function show(Request $request, string $project)
    {
        return response()->json($this->payload($this->design->project($request->user(), $project)))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $project)
    {
        $record = $this->design->project($request->user(), $project);
        abort_if(strlen($request->getContent()) > WorkspaceDesignService::MAX_BYTES + 4096, 413, 'Project request exceeds 2 MiB.');
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1', 'max:4294967294'],
            'name' => ['required', 'string', 'max:180'], 'document' => ['required', 'array'],
        ]);
        $document = $this->design->validateDocument($data['document'], $record);
        $response = DB::transaction(function () use ($request, $record, $data, $document) {
            $locked = $this->design->owned($request->user())->whereKey($record->id)->lockForUpdate()->firstOrFail();
            if ($locked->revision !== (int) $data['revision']) {
                return response()->json(['message' => 'Revision conflict. Your local edits were not overwritten; reload or export them first.', 'revision' => $locked->revision], 409);
            }
            $locked->update(['name' => $data['name'], 'document' => $document, 'revision' => $locked->revision + 1]);
            return response()->json($this->payload($locked));
        });
        return $response->header('Cache-Control', 'private, no-store');
    }

    public function attachAsset(Request $request, string $project)
    {
        $record = $this->design->project($request->user(), $project);
        $data = $request->validate(['file' => ['required', 'uuid']]);
        $drive = app(WorkspaceDriveService::class);
        $file = WorkspaceFile::query()->where('public_id', $data['file'])->where('owner_id', $request->user()->id)->where('school_id', $record->school_id)->firstOrFail();
        $version = $drive->currentVersion($request->user(), $file);
        abort_unless($version->disk === 'workspace' && in_array($version->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true), 422, 'Only private PNG, JPEG and WebP images are supported.');
        app(WorkspaceMalwareScanService::class)->assertClean($version);
        $asset = DB::transaction(function () use ($request, $record, $version) {
            $this->design->owned($request->user())->whereKey($record->id)->lockForUpdate()->firstOrFail();
            $query = WorkspaceDesignAsset::query()->where('workspace_design_project_id', $record->id);
            $existing = (clone $query)->where('workspace_file_version_id', $version->id)->first();
            if ($existing) return $existing;
            abort_if($query->count() >= 100, 422, 'Asset limit reached (100).');
            return WorkspaceDesignAsset::create(['public_id' => (string) Str::uuid(), 'workspace_design_project_id' => $record->id, 'workspace_file_version_id' => $version->id]);
        });
        return response()->json(['url' => '/workspace/design/'.$record->public_id.'/assets/'.$asset->public_id], 201)->header('Cache-Control', 'private, no-store');
    }

    public function asset(Request $request, string $project, string $asset)
    {
        $record = $this->design->project($request->user(), $project);
        $binding = WorkspaceDesignAsset::query()->where('public_id', $asset)->where('workspace_design_project_id', $record->id)->firstOrFail();
        $version = WorkspaceFileVersion::query()->with('file')->findOrFail($binding->workspace_file_version_id);
        abort_unless($version->file, 404);
        app(WorkspaceDriveService::class)->assertOwner($request->user(), $version->file);
        abort_unless($version->disk === 'workspace' && in_array($version->mime_type, ['image/png', 'image/jpeg', 'image/webp'], true), 404);
        app(WorkspaceMalwareScanService::class)->assertClean($version);
        $stream = Storage::disk('workspace')->readStream($version->path);
        abort_if($stream === false, 404);
        return response()->stream(function () use ($stream): void {
            try { fpassthru($stream); } finally { if (is_resource($stream)) fclose($stream); }
        }, 200, ['Content-Type' => $version->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    private function payload(WorkspaceDesignProject $project): array
    {
        return ['id' => $project->public_id, 'name' => $project->name, 'document' => $project->document, 'revision' => $project->revision];
    }
}
