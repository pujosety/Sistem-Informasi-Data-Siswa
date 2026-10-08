<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'lms_lessons';

    protected $fillable = [
        'school_id', 'course_id', 'module_id', 'title', 'body', 'position', 'status',
        'attachment_path', 'external_url', 'video_url',
    ];

    protected $casts = ['position' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(LmsModule::class, 'module_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }
}
