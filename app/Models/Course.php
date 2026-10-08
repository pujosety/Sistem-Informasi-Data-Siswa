<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends \Illuminate\Database\Eloquent\Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const ARCHIVED = 'archived';

    protected $table = 'lms_courses';

    protected $fillable = [
        'school_id', 'academic_year_id', 'semester_id', 'subject_id', 'classroom_id',
        'teacher_id', 'code', 'title', 'description', 'status',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('position');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(LmsModule::class)->orderBy('position');
    }

    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(LmsAnnouncement::class)->latest('published_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LmsAssignment::class)->latest('due_at');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(LmsQuiz::class)->latest('due_at');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::PUBLISHED);
    }
}
