<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    use BelongsToSchool;

    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';

    protected $table = 'lms_course_enrollments';

    protected $fillable = ['school_id', 'course_id', 'student_id', 'enrolled_at', 'status'];

    protected $casts = ['enrolled_at' => 'datetime'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
