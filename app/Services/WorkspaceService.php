<?php

namespace App\Services;

/**
 * User tiers and workspaces.
 *
 * A user's TIER is the level of the product they belong to; a WORKSPACE is the
 * concrete surface they land on. Tiers are deliberately separate from roles:
 *
 *   - ROLE      what a person is (kesiswaan, operator, …)
 *   - ASSIGNMENT what they are responsible for (homeroom of X RPL 1)
 *   - WORKSPACE the surface their day starts on
 *
 * Wali Kelas is the reason this exists. A teacher is `kesiswaan` AND homeroom
 * of a class; requiring a second account (or a second role) for that was the
 * mistake this service removes. Their workspace list therefore contains BOTH
 * the kesiswaan workspace and "Kelas Saya".
 */
class WorkspaceService
{
    public const TIER_SYSTEM = 'system';
    public const TIER_MANAGEMENT = 'management';
    public const TIER_OPERATIONAL = 'operational';
    public const TIER_PORTAL = 'portal';
    public const TIER_ASSIGNED = 'assigned';

    /**
     * Every workspace the user can reach, in priority order.
     *
     * The FIRST entry is the deterministic landing workspace; `switcher` marks
     * which ones deserve a visible switcher entry.
     *
     * @return array<int, array{key:string,label:string,route:string,icon:string,tier:string,assigned:bool,switcher:bool}>
     */
    public function forUser(?\App\Models\User $user): array
    {
        if (! $user || ! $user->isActive()) {
            return [];
        }

        $workspaces = [];

        /*
         | The PRIMARY workspace is chosen by ROLE, not by "can this permission".
         | Deriving it from permissions produced nonsense: an Admin holds
         | student.view (so the Kesiswaan workspace) and verification.approve
         | (so the Verifikator workspace) but not user.view, which meant an Admin
         | landed on the Kesiswaan dashboard and never saw their own.
         |
         | Permissions still decide whether a workspace is OFFERED; the role
         | decides which one is the landing surface. Those are different
         | questions and conflating them is what broke login routing.
         */
        $primary = $this->primaryWorkspace($user);

        if ($primary) {
            $workspaces[] = $primary;
        }

        // --- ASSIGNED (contextual, not a role) ----------------------------
        // A Wali Kelas reaches this through an ASSIGNMENT, so it appears for
        // anyone homerooming a class regardless of their primary role — and
        // becomes their landing surface when they have no other workspace.
        $alreadyPrimary = ($primary['key'] ?? null) === 'kelas-saya';

        if (! $alreadyPrimary && $user->can('classroom.view') && $this->homeroomCount($user) > 0) {
            array_splice($workspaces, 1, 0, [$this->ws(
                'kelas-saya',
                'Kelas Saya',
                'academic.homeroom.index',
                'users-round',
                self::TIER_ASSIGNED,
                true,
            )]);
        }

        // --- PORTAL -------------------------------------------------------
        if ($this->isParent($user)) {
            array_splice($workspaces, 1, 0, [$this->ws(
                'parent', 'Orang Tua/Wali', 'parent.dashboard', 'heart-handshake', self::TIER_PORTAL, false,
            )]);
        }

        // The switcher is only meaningful when there is genuinely a choice.
        $count = count($workspaces);
        $i = 0;

        return array_map(function (array $ws) use (&$i, $count) {
            $ws['switcher'] = $count > 1 && $i === 0;
            $i++;

            return $ws;
        }, $workspaces);
    }

    /**
     * The landing surface, decided by role.
     *
     * Order is significant: the most senior role wins, so a user holding both
     * `admin` and `verifikator` lands on administration rather than the queue.
     */
    private function primaryWorkspace(\App\Models\User $user): ?array
    {
        // --- SYSTEM -------------------------------------------------------
        if ($user->isSuperAdmin()) {
            return $this->ws('super-admin', 'Super Admin', 'workspace.admin', 'shield-check', self::TIER_SYSTEM, false);
        }

        // --- MANAGEMENT ---------------------------------------------------
        // A user who can manage roles IS an administrator; a user who can only
        // verify is a verifier. Splitting them by the user.* permission was
        // wrong: Admin legitimately lacks user.*, which is Super Admin's alone.
        if ($user->hasRole('admin')) {
            return $this->ws('admin', 'Admin', 'workspace.admin', 'settings', self::TIER_MANAGEMENT, false);
        }

        if ($user->hasRole('kesiswaan')) {
            return $this->ws('kesiswaan', 'Kesiswaan', 'workspace.kesiswaan', 'briefcase', self::TIER_MANAGEMENT, false);
        }

        // --- PORTAL beats internal roles ----------------------------------
        // Someone who is a guardian lands on their children, even if the same
        // account also holds an internal role: the portal is the job they were
        // given. The internal workspace is still offered as a secondary entry
        // in forUser(), so nothing becomes unreachable.
        if ($this->isParent($user)) {
            return $this->ws('parent', 'Orang Tua/Wali', 'parent.dashboard', 'heart-handshake', self::TIER_PORTAL, false);
        }

        // --- OPERATIONAL --------------------------------------------------
        if ($user->hasRole('verifikator')) {
            return $this->ws('verifikator', 'Verifikator', 'workspace.verifikator', 'clipboard-check', self::TIER_OPERATIONAL, false);
        }

        if ($user->hasRole('operator')) {
            return $this->ws('operator', 'Operator', 'workspace.operator', 'clipboard-list', self::TIER_OPERATIONAL, false);
        }

        if ($user->hasRole('wali_kelas') && $this->homeroomCount($user) > 0) {
            return $this->ws('kelas-saya', 'Kelas Saya', 'academic.homeroom.index', 'users-round', self::TIER_ASSIGNED, true);
        }

        // --- PORTAL -------------------------------------------------------
        if ($user->isStudent()) {
            return $this->ws('siswa', 'Siswa', 'siswa.dashboard', 'graduation-cap', self::TIER_PORTAL, false);
        }

        return null;
    }

    /**
     * Where this user should land after login.
     *
     * Deterministic: the first workspace wins, in the order forUser() returns.
     * A user with no workspace at all lands on their profile rather than on a
     * login loop, which is what a bare `login` default used to cause.
     */
    public function primaryFor(?\App\Models\User $user): ?array
    {
        return $this->forUser($user)[0] ?? null;
    }

    public function primaryRouteFor(?\App\Models\User $user): ?string
    {
        return $this->primaryFor($user)['route'] ?? null;
    }

    public function tierFor(?\App\Models\User $user): string
    {
        return $this->primaryFor($user)['tier'] ?? self::TIER_PORTAL;
    }

    /** Human tier label, used in the workspace switcher. */
    public function tierLabel(string $tier): string
    {
        return match ($tier) {
            self::TIER_SYSTEM => 'Sistem',
            self::TIER_MANAGEMENT => 'Manajemen',
            self::TIER_OPERATIONAL => 'Operasional',
            self::TIER_PORTAL => 'Portal',
            self::TIER_ASSIGNED => 'Penugasan',
            default => 'Lainnya',
        };
    }

    // ------------------------------------------------------------------ helpers

    private function ws(string $key, string $label, string $route, string $icon, string $tier, bool $assigned): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'route' => $route,
            'icon' => $icon,
            'tier' => $tier,
            'assigned' => $assigned,
            'switcher' => false,
        ];
    }

    private function homeroomCount(\App\Models\User $user): int
    {
        return \App\Models\HomeroomAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', \App\Models\HomeroomAssignment::ACTIVE)
            ->count();
    }

    /**
     * A parent is a user with an active guardian link — never a role. That keeps
     * Wali Murid structurally separate from every internal role.
     */
    private function isParent(\App\Models\User $user): bool
    {
        return \App\Models\GuardianRelationship::query()
            ->where('guardian_user_id', $user->id)
            ->where('status', 'active')
            ->exists();
    }
}
