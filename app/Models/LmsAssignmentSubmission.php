<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class LmsAssignmentSubmission extends Model
{
    use BelongsToSchool;

    public const SUBMITTED = 'submitted';
    public const GRADED = 'graded';

    protected $table = 'lms_assignment_submissions';
    protected $fillable = [
        'school_id', 'assignment_id', 'student_id', 'body', 'file_path', 'submitted_at',
        'is_late', 'score', 'feedback', 'graded_by', 'graded_at', 'status',
    ];
    protected $casts = [
        'submitted_at' => 'datetime', 'is_late' => 'boolean', 'score' => 'decimal:2', 'graded_at' => 'datetime',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function assignment(): BelongsTo { return $this->belongsTo(LmsAssignment::class, 'assignment_id'); }
    public function student(): BelongsTo { return $this->belongsTo(Student::class); }
    public function grader(): BelongsTo { return $this->belongsTo(User::class, 'graded_by'); }
}
