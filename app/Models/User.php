<?php

namespace App\Models;

use App\Services\PermissionCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'phone', 'is_active', 'disabled_reason'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    protected $attributes = [
        'is_active' => true,
    ];

    // -----------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    // -----------------------------------------------------------------
    // Roles
    // -----------------------------------------------------------------

    /**
     * Wali Murid links — the CHILDREN this user is a guardian of.
     *
     * Deliberately separate from HomeroomAssignment: a Wali Kelas teaches a
     * classroom, a Wali Murid looks after specific students.
     */
    public function guardianRelationships(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GuardianRelationship::class, 'guardian_user_id');
    }

    /** Classrooms this user currently homerooms. */
    public function homeroomAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HomeroomAssignment::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['super_admin', 'admin', 'kesiswaan', 'operator', 'verifikator']);
    }

    public function isStudent(): bool
    {
        return $this->hasRole('siswa');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Single source of truth for "may this account do X".
     *
     * Implemented on the model rather than through Gate::before because Spatie
     * registers its own Gate::before during the provider register() phase, so
     * a callback registered later in boot() was never consulted — which left
     * Super Admin denied everything. Overriding here also keeps behaviour
     * identical in HTTP, console and tests, which matters because the
     * permission matrix UI calls this method directly.
     */
    public function can($abilities, $arguments = []): bool
    {
        $abilities = is_array($abilities) ? $abilities : [$abilities];

        // A disabled account is denied everything, even with valid permissions.
        if (! $this->isActive()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        // Anything that is not a BARE permission name is a policy/Gate check and
        // must be delegated to the parent.
        //
        // Two shapes arrive here:
        //   - a resolved ability, "App\Policies\ClassroomPolicy@view" (or a
        //     closure, "Some\Gate@check")
        //   - a plain ability WITH a model argument, $user->can('view', $class)
        //
        // The second one is the trap: "view" looks exactly like a permission
        // name, so looking it up in Spatie returned false and every policy check
        // 403'd. Any non-empty argument list means a policy is being consulted.
        if ($arguments !== [] && $arguments !== null) {
            return parent::can($abilities, $arguments);
        }

        foreach ($abilities as $ability) {
            if (! is_string($ability)
                || $ability === ''
                || str_contains($ability, '@')
                || str_contains($ability, ')')
                || str_contains($ability, '::')) {
                return parent::can($abilities, $arguments);
            }
        }

        foreach ($abilities as $ability) {
            try {
                if ($this->hasPermissionTo($ability)) {
                    return true;
                }
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
                // An unknown permission must never grant access.
                continue;
            }
        }

        return false;
    }

    /**
     * Where a freshly authenticated user belongs.
     *
     * Ordered by how much the account is allowed to do, so an operator lands
     * on an analytics view rather than an admin screen they cannot use.
     */
    public function homeRoute(): string
    {
        return match (true) {
            $this->can('dashboard.admin.view') => 'admin.dashboard',
            $this->can('student.view') => 'kesiswaan.students',
            $this->isStudent() => 'siswa.dashboard',

            // A staff account with no landing page of its own still needs a
            // valid destination. Never 'login': the guest middleware would
            // bounce it back to /dashboard and loop forever.
            default => 'profile.edit',
        };
    }

    /**
     * Roles this user is ALLOWED to hand out.
     *
     * Only a Super Admin may assign Super Admin. A plain Admin can never
     * escalate itself or anyone else, no matter what a request body claims.
     */
    public function assignableRoles(): array
    {
        if ($this->isSuperAdmin()) {
            return array_merge(PermissionCatalog::assignableRoles(), ['super_admin']);
        }

        return PermissionCatalog::assignableRoles();
    }

    public function canAssignRole(string $role): bool
    {
        if (! $this->can('role.assign')) {
            return false;
        }

        return in_array($role, $this->assignableRoles(), true);
    }

    /**
     * Super Admin protection.
     *
     * The last remaining Super Admin may neither be demoted, disabled, nor
     * deleted, otherwise the installation would be unadministrable.
     */
    public function isLastSuperAdmin(): bool
    {
        if (! $this->isSuperAdmin()) {
            return false;
        }

        return self::role('super_admin')->count() <= 1;
    }

    public function canBeDemotedBy(?User $actor): bool
    {
        if ($this->isLastSuperAdmin()) {
            return false;
        }

        return $actor?->isSuperAdmin() === true;
    }

    public function canBeDisabledBy(?User $actor): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        // Nobody may lock themselves out, and the last Super Admin stays.
        if ($actor && $actor->id === $this->id) {
            return false;
        }

        if ($this->isLastSuperAdmin()) {
            return false;
        }

        // Disabling a Super Admin requires Super Admin authority.
        if ($this->isSuperAdmin()) {
            return $actor?->isSuperAdmin() === true;
        }

        return $actor?->can('user.disable') === true;
    }

    public function canBeDeletedBy(?User $actor): bool
    {
        if ($this->isLastSuperAdmin()) {
            return false;
        }

        if ($actor && $actor->id === $this->id) {
            return false;
        }

        return $actor?->can('user.delete') === true;
    }
}
