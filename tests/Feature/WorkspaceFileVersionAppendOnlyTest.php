<?php

namespace Tests\Feature;

use App\Models\WorkspaceFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceFileVersionAppendOnlyTest extends TestCase
{
    public function test_existing_version_cannot_be_updated_or_deleted_but_relationship_create_remains_available(): void
    {
        $schoolId = DB::table('schools')->value('id');
        $owner = \App\Models\User::factory()->create();
        $file = WorkspaceFile::create([
            'public_id' => (string) Str::uuid(), 'school_id' => $schoolId, 'owner_id' => $owner->id,
            'name' => 'test.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'current_version' => 1,
        ]);
        $version = $file->versions()->create([
            'version_number' => 1, 'disk' => 'workspace', 'path' => 'versions/one.txt',
            'mime_type' => 'text/plain', 'size_bytes' => 1, 'created_by' => $owner->id,
        ]);

        try {
            $version->update(['path' => 'versions/changed.txt']);
            $this->fail('Updating an existing version should be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Workspace file versions are append-only; create a new version instead.', $exception->getMessage());
        }

        try {
            $version->delete();
            $this->fail('Deleting an existing version should be rejected.');
        } catch (\LogicException $exception) {
            $this->assertSame('Workspace file versions are append-only; create a new version instead.', $exception->getMessage());
        }

        $this->assertDatabaseHas('workspace_file_versions', ['id' => $version->id, 'path' => 'versions/one.txt']);
        $this->assertSame(1, $file->versions()->count());
    }
}