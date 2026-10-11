<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceVersionScan extends Model
{
    protected $fillable = ['workspace_file_version_id', 'status', 'reason_code', 'checksum_sha256', 'scanned_bytes', 'attempts', 'scanned_at'];
    protected $hidden = ['reason_code', 'checksum_sha256'];
    protected $casts = ['scanned_bytes' => 'integer', 'attempts' => 'integer', 'scanned_at' => 'datetime'];
}
