<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LmsAssignment extends Model
{
    use BelongsToSchool;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const CLOSED = 'closed';

    protected $table = 'lms_assignments';
    protected $fillable = [
        'school_id', 'course_id', 'module_id', 'title', 'description', 'starts_at', 'due_at',
        'max_score', 'submission_type', 'max_attempts', 'allow_late', 'status',
    ];
    protected $casts = [
        'starts_at' => 'datetime', 'due_at' => 'datetime', 'max_score' => 'decimal:2',
        'max_attempts' => 'integer', 'allow_late' => 'boolean',
    ];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function module(): BelongsTo { return $this->belongsTo(LmsModule::class); }
    public function submissions(): HasMany { return $this->hasMany(LmsAssignmentSubmission::class, 'assignment_id'); }
}
