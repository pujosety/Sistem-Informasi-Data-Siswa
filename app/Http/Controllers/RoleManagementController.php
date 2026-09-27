<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * ADMIN → ROLE & HAK AKSES
 *
 * Two hard rules enforced here:
 *  1. Protected roles (super_admin, admin, siswa) cannot be deleted.
 *  2. Nobody may grant a permission they do not hold themselves, which
 *     prevents an Admin from silently becoming more powerful than a
 *     Super Admin by editing the matrix directly.
 */
class RoleManagementController extends BaseController
{
    public function __construct(AuditService $audit, CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    public function index(): View
    {
        $roles = Role::with('permissions')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', [
            'roles' => $roles,
            'protected' => PermissionCatalog::protectedRoles(),
        ]);
    }

    public function edit(Role $role): View
    {
        abort_if($role->name === 'super_admin', 404, 'Super Admin selalu memiliki seluruh izin.');

        $actor = request()->user();

        $all = Permission::orderBy('name')->get();

        return view('admin.roles.edit', [
            'role' => $role->load('permissions'),
            'domains' => PermissionCatalog::domains(),
            'granted' => $role->permissions->pluck('name')->all(),
            // A user can only hand out what they themselves hold.
            'assignable' => $actor->isSuperAdmin() ? $all->pluck('name')->all() : $actor->getAllPermissions()->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'super_admin', 403, 'Hak akses Super Admin tidak dapat diubah.');

        $actor = $request->user();
        $requested = (array) $request->input('permissions', []);

        // Privilege-escalation guard: an Admin may not grant what it lacks.
        if (! $actor->isSuperAdmin()) {
            $held = $actor->getAllPermissions()->pluck('name')->all();
            $forbidden = array_diff($requested, $held);

            if ($forbidden !== []) {
                abort(403, 'Anda tidak dapat memberikan izin yang tidak Anda miliki sendiri.');
            }
        }

        $role->syncPermissions(array_values(array_intersect($requested, Permission::pluck('name')->all())));

        $this->audit->log('role.permissions_changed', $role, "Mengubah hak akses role {$role->name}", [
            'granted' => count($requested),
        ]);

        return back()->with('success', "Hak akses role {$role->name} diperbarui.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', 'unique:roles,name'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $role = Role::create(['name' => $data['name']]);

        $this->audit->log('role.created', $role, "Membuat role baru: {$data['name']}");

        return redirect()
            ->route('admin.roles.edit', $role)
            ->with('success', "Role {$data['name']} dibuat. Atur hak aksesnya di bawah.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, PermissionCatalog::protectedRoles(), true)) {
            return back()->with('error', "Role {$role->name} dilindungi dan tidak dapat dihapus.");
        }

        if ($role->users()->exists()) {
            return back()->with('error', "Role {$role->name} masih memiliki pengguna. Pindahkan mereka dulu.");
        }

        $name = $role->name;
        $role->delete();

        $this->audit->log('role.deleted', null, "Menghapus role {$name}");

        return back()->with('success', "Role {$name} dihapus.");
    }
}
