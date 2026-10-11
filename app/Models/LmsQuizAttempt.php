<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LmsQuizAttempt extends Model
{
    use BelongsToSchool;
    protected $table = 'lms_quiz_attempts';
    protected $fillable = ['school_id', 'quiz_id', 'student_id', 'started_at', 'submitted_at', 'score', 'answers', 'status'];
    protected $casts = ['started_at' => 'datetime', 'submitted_at' => 'datetime', 'score' => 'decimal:2', 'answers' => 'array'];
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function quiz(): BelongsTo { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
