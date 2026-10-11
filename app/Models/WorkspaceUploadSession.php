<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** No implicit tenant scope: HTTP service always applies both owner and school. */
class WorkspaceUploadSession extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['temp_key', 'owner_id', 'school_id', 'parent_public_id', 'receipts', 'target_public_id'];

    protected function casts(): array
    {
        return [
            'total_bytes' => 'integer', 'chunk_bytes' => 'integer',
            'received_bytes' => 'integer', 'next_index' => 'integer',
            'reserved_bytes' => 'integer', 'receipts' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
