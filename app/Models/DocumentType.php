<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class DocumentType extends Model
{
    use HasFactory;

    protected $table = 'document_types';

    protected $fillable = [
        'name', 'slug', 'label', 'description', 'accepted_mimes',
        'max_size_kb', 'is_required', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'accepted_mimes' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function mimes(): string
    {
        $mimes = $this->accepted_mimes ?: ['jpg', 'jpeg', 'png', 'pdf'];
        $mimes[] = 'pdf';

        return implode(',', array_unique($mimes));
    }
}
