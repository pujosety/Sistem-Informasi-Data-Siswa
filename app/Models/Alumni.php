<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alumni is a VIEW over graduated students, not a copy of them. The student row
 * and every historical enrollment are preserved; this table only records the
 * exit facts.
 */
class Alumni extends Model
{
    use HasFactory;

    protected $table = 'alumni';

    protected $fillable = [
        'student_id', 'graduation_year', 'graduation_date',
        'last_classroom_id', 'department_id', 'notes',
    ];

    protected $casts = [
        'graduation_date' => 'date',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lastClassroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'last_classroom_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeYear(Builder $query, ?int $year): Builder
    {
        return $year ? $query->where('graduation_year', $year) : $query;
    }
}
