<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LmsAnnouncement extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'lms_announcements';
    protected $fillable = [
        'school_id', 'course_id', 'author_id', 'title', 'body', 'status', 'published_at',
    ];
    protected $casts = ['published_at' => 'datetime'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED)->whereNotNull('published_at');
    }
}
