<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model
{
    use BelongsToSchool;

    protected $table = 'lms_lesson_progress';
    protected $fillable = ['school_id', 'course_id', 'lesson_id', 'student_id', 'completed_at'];
    protected $casts = ['completed_at' => 'datetime'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function lesson(): BelongsTo { return $this->belongsTo(Lesson::class); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
}
