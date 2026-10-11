<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceFileShare extends Model
{
    use BelongsToSchool, SoftDeletes;

    protected $fillable = ['public_id', 'school_id', 'workspace_file_id', 'owner_id', 'recipient_id', 'expires_at', 'revoked_at'];

    protected $casts = ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFile::class, 'workspace_file_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isActive(): bool
    {
        return ! $this->trashed() && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
