<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $table = 'subjects';

    protected $fillable = ['name', 'code', 'grade_level'];

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }
}
