<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A grade belongs to an ENROLLMENT, not a Student directly, so a score always
 * carries the classroom and academic year it was earned in.
 */
class Grade extends Model
{
    use HasFactory;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'grades';

    protected $fillable = [
        'enrollment_id', 'subject_id', 'term', 'score', 'status',
        'teacher_id', 'published_by', 'published_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function predicateLabel(): string
    {
        $score = (float) $this->score;

        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }
}
