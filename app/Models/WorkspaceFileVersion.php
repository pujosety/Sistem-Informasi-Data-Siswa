<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WorkspaceFileVersion extends Model
{
    protected $table = 'workspace_file_versions';

    protected $fillable = [
        'workspace_file_id', 'version_number', 'disk', 'path', 'mime_type',
        'size_bytes', 'checksum_sha256', 'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Workspace file versions are append-only; create a new version instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Workspace file versions are append-only; create a new version instead.');
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
