<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'lms_lessons';

    protected $fillable = ['course_id', 'title', 'body', 'position', 'status'];

    protected $casts = ['position' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }
}
