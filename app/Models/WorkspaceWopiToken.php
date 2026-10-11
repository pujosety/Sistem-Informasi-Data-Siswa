<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceWopiToken extends Model
{
    protected $table = 'workspace_wopi_tokens';

    protected $fillable = [
        'workspace_file_id', 'school_id', 'user_id', 'token_hash', 'can_write',
        'expires_at', 'last_used_at', 'revoked_at',
    ];

    protected $casts = [
        'can_write' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
