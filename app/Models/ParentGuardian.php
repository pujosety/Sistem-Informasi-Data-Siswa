<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentGuardian extends Model
{
    use HasFactory;

    // "parents" is safe in MySQL, but keep the explicit table for clarity.
    protected $table = 'parents';

    protected $fillable = ['student_id', 'relation', 'full_name', 'job', 'phone', 'nik', 'address'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function relationLabel(): string
    {
        return match ($this->relation) {
            'father' => 'Ayah',
            'mother' => 'Ibu',
            'guardian' => 'Wali',
            default => '-',
        };
    }
}
