<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceDesignProject extends Model
{
    protected $fillable = ['public_id', 'owner_id', 'school_id', 'name', 'document', 'revision'];
    protected $hidden = ['id', 'owner_id', 'school_id'];
    protected $casts = ['document' => 'array', 'revision' => 'integer'];
    public function getRouteKeyName(): string { return 'public_id'; }
}
