<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceFolder extends Model
{
    use BelongsToSchool;
    use SoftDeletes;

    protected $table = 'workspace_folders';

    protected $fillable = [
        'public_id', 'school_id', 'owner_id', 'parent_folder_id', 'name',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_folder_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_folder_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkspaceFile::class, 'parent_folder_id');
    }
}
