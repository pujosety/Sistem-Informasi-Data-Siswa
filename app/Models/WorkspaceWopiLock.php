<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceWopiLock extends Model
{
    protected $table = 'workspace_wopi_locks';
    protected $primaryKey = 'workspace_file_id';
    public $incrementing = false;

    protected $fillable = [
        'workspace_file_id', 'lock_value', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }
}
