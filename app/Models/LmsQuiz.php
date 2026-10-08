<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LmsQuiz extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CLOSED = 'closed';

    protected $table = 'lms_quizzes';
    protected $fillable = ['school_id', 'course_id', 'title', 'description', 'starts_at', 'due_at', 'duration_minutes', 'attempt_limit', 'passing_score', 'shuffle_questions', 'show_results', 'status'];
    protected $casts = ['starts_at' => 'datetime', 'due_at' => 'datetime', 'duration_minutes' => 'integer', 'attempt_limit' => 'integer', 'passing_score' => 'decimal:2', 'shuffle_questions' => 'boolean', 'show_results' => 'boolean'];
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function questions(): HasMany { return $this->hasMany(LmsQuizQuestion::class, 'quiz_id')->orderBy('position'); }
    public function attempts(): HasMany { return $this->hasMany(LmsQuizAttempt::class, 'quiz_id'); }
}
