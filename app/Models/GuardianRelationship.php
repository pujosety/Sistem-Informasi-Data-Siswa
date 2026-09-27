<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Links a parent LOGIN to a student — this is Wali Murid, and is deliberately
 * separate from HomeroomAssignment (Wali Kelas).
 *
 * One guardian user may have many children; one student may have many guardians.
 */
class GuardianRelationship extends Model
{
    use HasFactory;

    public const ACTIVE = 'active';

    public const RELATIONSHIPS = [
        'ayah' => 'Ayah',
        'ibu' => 'Ibu',
        'wali' => 'Wali',
    ];

    protected $table = 'guardian_relationships';

    protected $fillable = [
        'student_id',
        'guardian_user_id',
        'parent_id',
        'relationship',
        'is_primary',
        'status',
        'linked_via',
        'invite_token_hash',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'verified_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function guardianUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_user_id');
    }

    public function parentProfile(): BelongsTo
    {
        return $this->belongsTo(ParentGuardian::class, 'parent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::ACTIVE);
    }

    public function relationshipLabel(): string
    {
        return self::RELATIONSHIPS[$this->relationship] ?? ucfirst($this->relationship);
    }

    /** Student ids this guardian user may access. */
    public static function studentIdsFor(int $userId): array
    {
        return self::query()
            ->where('guardian_user_id', $userId)
            ->where('status', self::ACTIVE)
            ->pluck('student_id')
            ->all();
    }
}
