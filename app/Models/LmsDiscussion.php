<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class LmsDiscussion extends Model
{
    use BelongsToSchool;

    protected $table = 'lms_discussions';
    protected $fillable = ['school_id', 'course_id', 'author_id', 'parent_id', 'body'];

    public function school(): BelongsTo { return $this->belongsTo(School::class); }
    public function course(): BelongsTo { return $this->belongsTo(Course::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->oldest(); }
}
