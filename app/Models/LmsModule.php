<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LmsModule extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';

    protected $table = 'lms_modules';
    protected $fillable = ['school_id', 'course_id', 'title', 'description', 'position', 'status'];
    protected $casts = ['position' => 'integer'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function lessons(): HasMany { return $this->hasMany(Lesson::class, 'module_id')->orderBy('position'); }
}
