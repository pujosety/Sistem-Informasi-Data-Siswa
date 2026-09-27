<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends BaseController
{
    public function __construct(
        AuditService $audit,
        CompletenessService $completeness,
        private readonly DocumentService $documents,
    ) {
        parent::__construct($audit, $completeness);
    }

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Email atau password tidak cocok.',
            ]);
        }

        $user = $request->user();

        // A disabled account must not stay signed in.
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $this->audit->log('auth.login_rejected', $user, 'Percobaan login ditolak: akun dinonaktifkan');

            throw ValidationException::withMessages([
                'email' => 'Akun Anda telah dinonaktifkan. Hubungi administrator.',
            ]);
        }

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $this->audit->log('auth.login', $user, 'Login berhasil');
        $this->ensureStudentProfile($user);

        return redirect()->intended(route($user->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->audit->log('auth.logout', $request->user(), 'Logout');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Anda telah keluar.');
    }

    /** A siswa account without a student row gets a shell so the portal works. */
    private function ensureStudentProfile(\App\Models\User $user): void
    {
        if (! $user->isStudent() || $user->student()->exists()) {
            return;
        }

        $student = \App\Models\Student::create([
            'user_id' => $user->id,
            'nisn' => (string) (1000000000 + $user->id),
            'full_name' => $user->name,
        ]);

        $year = \App\Models\AcademicYear::where('is_active', true)->first()
            ?? \App\Models\AcademicYear::orderByDesc('start_date')->first();

        if (! $year) {
            return;
        }

        $registration = \App\Models\Registration::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => \App\Models\Registration::STATUS_DRAFT,
        ]);

        $this->documents->seedPlaceholders($registration);
        $this->completeness->refresh($registration);
    }
}
