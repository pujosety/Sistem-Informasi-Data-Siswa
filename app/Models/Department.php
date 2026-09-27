<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';

    protected $fillable = ['name', 'code'];

    /** Classes (rombel) in this department. */
    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    /** Enrollments recorded against this department. */
    public function enrollments()
    {
        return $this->hasMany(Enrollment::class);
    }
}
