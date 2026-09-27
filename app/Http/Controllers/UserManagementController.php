<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\PermissionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * ADMIN → PENGGUNA
 *
 * Accounts are disabled rather than deleted, so verification history and
 * audit trails survive a staff member leaving.
 */
class UserManagementController extends BaseController
{
    public function __construct(AuditService $audit, CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    public function index(Request $request): View
    {
        $users = User::with('roles')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('email', 'like', '%'.$term.'%')))
            ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('name', $request->string('role'))))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->string('status') === 'active'))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'roles' => \Spatie\Permission\Models\Role::orderBy('name')->pluck('name'),
            'q' => $request->string('q')->toString(),
            'filters' => $request->only(['role', 'status']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'roles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'email.unique' => 'Email ini sudah digunakan.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        /*
         | Server-side escalation guard. The form does not even offer
         | super_admin to a non-Super Admin, but a crafted payload could still
         | send it — so the check is repeated here where it cannot be skipped.
         */
        if (! $actor->canAssignRole($data['role'])) {
            abort(403, 'Anda tidak berwenang menetapkan role tersebut.');
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'email_verified_at' => now(),
            'is_active' => $request->boolean('is_active', true),
            'created_by' => $actor->id,
        ]);

        $user->assignRole($data['role']);

        $this->audit->log('user.created', $user, "Membuat pengguna {$user->name} dengan role {$data['role']}", [
            'role' => $data['role'],
        ]);

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', "Pengguna {$user->name} berhasil dibuat.");
    }

    public function show(User $user): View
    {
        $user->load('roles.permissions');

        return view('admin.users.show', [
            'user' => $user,
            'activity' => ActivityLog::where('subject_type', 'user')
                ->where('subject_id', $user->id)
                ->latest()
                ->limit(10)
                ->get(),
            'assignableRoles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', [
            'user' => $user->load('roles'),
            'roles' => auth()->user()->assignableRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data + ['phone' => $request->input('phone')]);

        $this->audit->log('user.updated', $user, "Mengubah data pengguna {$user->name}");

        return back()->with('success', 'Data pengguna diperbarui.');
    }

    public function updateRole(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        $data = $request->validate(['role' => ['required', 'string']]);

        if (! $actor->canAssignRole($data['role'])) {
            abort(403, 'Anda tidak berwenang menetapkan role tersebut.');
        }

        // The last Super Admin cannot be demoted out of existence.
        if ($user->isLastSuperAdmin() && $data['role'] !== 'super_admin') {
            return back()->with('error', 'Role Super Admin terakhir tidak dapat dihapus.');
        }

        $previous = $user->roles->pluck('name')->first();
        $user->syncRoles([$data['role']]);

        $this->audit->log('user.role_changed', $user, "Mengubah role {$user->name}: {$previous} → {$data['role']}", [
            'from' => $previous,
            'to' => $data['role'],
        ]);

        return back()->with('success', "Role {$user->name} diubah menjadi {$data['role']}.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($user->isActive()) {
            if (! $user->canBeDisabledBy($actor)) {
                return back()->with('error', $user->isLastSuperAdmin()
                    ? 'Super Admin terakhir tidak dapat dinonaktifkan.'
                    : 'Anda tidak berwenang menonaktifkan pengguna ini.');
            }

            $user->update([
                'is_active' => false,
                'disabled_reason' => $request->input('reason'),
            ]);

            $this->audit->log('user.disabled', $user, "Menonaktifkan akun {$user->name}");

            return back()->with('success', "Akun {$user->name} dinonaktifkan.");
        }

        // Reactivation is a routine action for anyone who may disable.
        if (! $actor->can('user.disable') && ! $actor->isSuperAdmin()) {
            abort(403, 'Anda tidak berwenang mengaktifkan kembali pengguna ini.');
        }

        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            abort(403, 'Hanya Super Admin yang dapat mengaktifkan kembali akun Super Admin.');
        }

        $user->update(['is_active' => true, 'disabled_reason' => null]);

        $this->audit->log('user.reactivated', $user, "Mengaktifkan kembali akun {$user->name}");

        return back()->with('success', "Akun {$user->name} diaktifkan kembali.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        // Never store the password itself — only that it happened.
        $this->audit->log('user.password_reset', $user, "Mengatur ulang password untuk {$user->name}");

        return back()->with('success', "Password untuk {$user->name} telah diatur ulang.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if (! $user->canBeDeletedBy($actor)) {
            return back()->with('error', 'Pengguna ini tidak dapat dihapus. Nonaktifkan sebagai gantinya.');
        }

        $name = $user->name;
        $user->delete();

        $this->audit->log('user.deleted', null, "Menghapus pengguna {$name}");

        return redirect()->route('admin.users.index')
            ->with('success', "Pengguna {$name} dihapus.");
    }
}
