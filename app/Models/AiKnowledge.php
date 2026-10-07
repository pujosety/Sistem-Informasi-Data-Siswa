<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class AiKnowledge extends Model
{
    use BelongsToSchool;

    protected $table = 'ai_knowledge';

    protected $fillable = [
        'school_id', 'source_type', 'source_id', 'title', 'content',
        'source_url', 'metadata', 'is_active', 'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
