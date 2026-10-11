<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LmsQuizQuestion extends Model
{
    use BelongsToSchool;
    protected $table = 'lms_quiz_questions';
    protected $fillable = ['school_id', 'quiz_id', 'type', 'prompt', 'options', 'correct_answer', 'points', 'position'];
    protected $casts = ['options' => 'array', 'points' => 'decimal:2', 'position' => 'integer'];
    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function quiz(): BelongsTo { return $this->belongsTo(LmsQuiz::class, 'quiz_id'); }
}
