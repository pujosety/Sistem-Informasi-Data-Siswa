<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkspaceFile extends Model
{
    use BelongsToSchool;
    use SoftDeletes;

    protected $table = 'workspace_files';

    protected $fillable = [
        'public_id', 'school_id', 'owner_id', 'parent_folder_id', 'name',
        'mime_type', 'size_bytes', 'source_application', 'current_version',
        'is_favorite', 'permission_metadata',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'current_version' => 'integer',
        'is_favorite' => 'boolean',
        'permission_metadata' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(WorkspaceFolder::class, 'parent_folder_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkspaceFileVersion::class, 'workspace_file_id')->orderByDesc('version_number');
    }

    public function currentVersionRecord(): ?WorkspaceFileVersion
    {
        return $this->versions->firstWhere('version_number', $this->current_version);
    }

    public function humanSize(): string
    {
        $size = (int) $this->size_bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return $unit === 0 ? $size.' '.$units[$unit] : number_format($size, 1).' '.$units[$unit];
    }
}
