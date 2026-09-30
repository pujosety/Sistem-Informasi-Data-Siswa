<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CompletenessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends BaseController
{
    public function __construct(AuditService $audit, CompletenessService $completeness)
    {
        parent::__construct($audit, $completeness);
    }

    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'string', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:25'],
        ]);

        // Changing the login email also updates the student's contact email.
        $user->update($data);

        $this->audit->log('profile.updated', $user, 'Profil akun diperbarui');

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /**
     * The signed-in person's own employment record.
     *
     * Read-only, and it resolves the record from the session rather than from
     * an id — there is deliberately no route parameter, because a parameter
     * here would be one more place where a missing check becomes an IDOR, and
     * the answer to "whose record?" is never a question worth asking on this
     * screen.
     *
     * The empty case is the common one and is NOT an error. Phase 3 created
     * `employees` and deliberately did not backfill, because guessing which of
     * the staff accounts is a teacher and which is a clerk writes false HR
     * records. So most staff have a login and no employment record yet, and
     * this screen has to say so rather than render an empty shell.
     */
    public function employment(Request $request): View
    {
        $user = $request->user();

        return view('profile.employment', [
            'user' => $user,
            'employee' => $user->employee,
            // Surfaced rather than resolved silently: a person who works here
            // and has no record is a task for the office, not a glitch the
            // employee should be reporting.
            'isStaff' => app(\App\Services\EmployeeService::class)->isStaff($user),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'min:8', 'max:72'],
        ], [
            'current_password.required' => 'Masukkan password saat ini.',
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Password saat ini tidak cocok.',
            ]);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        $this->audit->log('profile.password_changed', $user, 'Password diubah oleh pemilik akun');

        return back()->with('success', 'Password berhasil diubah.');
    }
}
