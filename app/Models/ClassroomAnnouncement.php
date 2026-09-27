<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomAnnouncement extends Model
{
    use HasFactory;

    public const AUDIENCES = [
        'students' => 'Siswa',
        'parents' => 'Orang Tua/Wali',
        'both' => 'Siswa & Orang Tua/Wali',
    ];

    protected $table = 'classroom_announcements';

    protected $fillable = [
        'classroom_id', 'academic_year_id', 'title', 'body', 'audience',
        'published_at', 'expires_at', 'created_by',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNotNull('published_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeVisibleTo(Builder $query, string $audience): Builder
    {
        return $query->whereIn('audience', ['both', $audience]);
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? ucfirst($this->audience);
    }
}
