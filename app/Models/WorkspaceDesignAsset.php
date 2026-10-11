<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceDesignAsset extends Model
{
    protected $fillable = ['public_id', 'workspace_design_project_id', 'workspace_file_version_id'];
    protected $hidden = ['id', 'workspace_design_project_id', 'workspace_file_version_id'];
}
