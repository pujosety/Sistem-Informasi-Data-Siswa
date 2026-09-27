<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_id', 'document_type_id', 'path', 'original_name', 'mime_type',
        'size_kb', 'status', 'rejection_reason', 'uploaded_at', 'reviewed_at', 'reviewed_by',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true);
    }
    public function url(): string
    {
        // Private files are streamed through DocumentFileController, which
        // checks ownership/role first. Never expose a raw /storage path.
        return route('documents.show', $this);
    }

    public function downloadUrl(): string
    {
        return route('documents.download', $this);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'valid' => 'Valid',
            'rejected' => 'Ditolak',
            'pending' => 'Menunggu',
            default => 'Belum diunggah',
        };
    }
}
