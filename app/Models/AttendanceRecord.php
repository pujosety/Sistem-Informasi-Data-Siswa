<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STATUSES = [
        'present' => 'Hadir',
        'late' => 'Terlambat',
        'sick' => 'Sakit',
        'excused' => 'Izin',
        'absent' => 'Alpa',
    ];

    /** Statuses that count as physically at school. */
    public const PRESENT_STATUSES = ['present', 'late'];

    protected $table = 'attendance_records';

    protected $fillable = [
        'attendance_session_id', 'enrollment_id', 'status', 'notes',
        'previous_status', 'correction_reason', 'corrected_by', 'corrected_at',
    ];

    protected $casts = [
        'corrected_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function student()
    {
        return $this->enrollment?->student;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function isPresent(): bool
    {
        return in_array($this->status, self::PRESENT_STATUSES, true);
    }

    public function wasCorrected(): bool
    {
        return $this->corrected_at !== null;
    }
}
