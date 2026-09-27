<?php

namespace App\Models;

use App\Services\CompletenessService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REVISION = 'revision';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_DRAFT => 'Belum Lengkap',
        self::STATUS_SUBMITTED => 'Terkirim',
        self::STATUS_PENDING => 'Menunggu Verifikasi',
        self::STATUS_REVISION => 'Perlu Perbaikan',
        self::STATUS_VERIFIED => 'Terverifikasi',
        self::STATUS_REJECTED => 'Ditolak',
    ];

    protected $fillable = [
        'student_id', 'academic_year_id', 'status', 'completeness',
        'submitted_at', 'verified_at', 'verified_by', 'admin_note',
    ];

    protected $casts = [
        'completeness' => 'integer',
        'submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function isEditableByStudent(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_REVISION], true);
    }

    public function isOpenForAdmin(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_REVISION], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Belum Lengkap',
            self::STATUS_SUBMITTED => 'Terkirim',
            self::STATUS_PENDING => 'Menunggu Verifikasi',
            self::STATUS_REVISION => 'Perlu Perbaikan',
            self::STATUS_VERIFIED => 'Terverifikasi',
            self::STATUS_REJECTED => 'Ditolak',
            default => '-',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_VERIFIED => 'green',
            self::STATUS_PENDING, self::STATUS_SUBMITTED => 'yellow',
            self::STATUS_REVISION, self::STATUS_REJECTED => 'red',
            default => 'gray',
        };
    }
}
