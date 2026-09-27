<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $table = 'attendance_sessions';

    protected $fillable = [
        'classroom_id', 'academic_year_id', 'date', 'recorded_by', 'locked_at',
    ];

    protected $casts = [
        'date' => 'date',
        'locked_at' => 'datetime',
    ];

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'classroom_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function records(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }
}
