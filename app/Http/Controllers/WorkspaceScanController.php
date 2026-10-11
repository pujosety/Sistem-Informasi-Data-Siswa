<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceFile;
use App\Services\WorkspaceDriveService;
use App\Services\WorkspaceMalwareScanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceScanController extends Controller
{
    public function retry(Request $request, WorkspaceFile $workspaceFile, int $version)
    {
        $drive = app(WorkspaceDriveService::class);
        $drive->assertOwner($request->user(), $workspaceFile);
        DB::transaction(function () use ($workspaceFile, $version, $request, $drive): void {
            $locked = WorkspaceFile::query()->whereKey($workspaceFile->id)->where('owner_id', $request->user()->id)->lockForUpdate()->firstOrFail();
            $drive->assertOwner($request->user(), $locked);
            $target = $locked->versions()->whereKey($version)->firstOrFail();
            app(WorkspaceMalwareScanService::class)->scan($target);
        });
        return redirect()->route('workspace.drive.index')->with('success', 'Pemeriksaan keamanan diulang. Berkas tetap dikarantina sampai hasilnya bersih.');
    }
}
