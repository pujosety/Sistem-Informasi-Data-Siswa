<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verification extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'verifications';

    protected $fillable = ['registration_id', 'document_id', 'admin_id', 'action', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'submit' => 'Mengirim pendaftaran',
            'approve' => 'Menyetujui',
            'reject' => 'Menolak',
            'revise' => 'Meminta perbaikan',
            'reset' => 'Mengulang verifikasi',
            default => $this->action,
        };
    }
}
