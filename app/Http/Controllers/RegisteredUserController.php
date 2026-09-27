<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudentRegistrationRequest;
use App\Services\AuditService;
use App\Services\CompletenessService;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisteredUserController extends BaseController
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
        if (auth()->check()) {
            return redirect()->route(auth()->user()->homeRoute());
        }

        $year = \App\Models\AcademicYear::where('is_active', true)->first()
            ?? \App\Models\AcademicYear::orderByDesc('start_date')->first();

        return view('auth.register', ['year' => $year]);
    }

    public function store(StudentRegistrationRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request) {
            $user = \App\Models\User::create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->toString(),
                'password' => Hash::make($request->string('password')->toString()),
                'email_verified_at' => now(), // school-issued account; no mail flow yet
            ]);

            $user->assignRole('siswa');

            $year = \App\Models\AcademicYear::where('is_active', true)->first()
                ?? \App\Models\AcademicYear::orderByDesc('start_date')->first();

            $student = \App\Models\Student::create([
                'user_id' => $user->id,
                'nisn' => $request->string('nisn')->toString(),
                'full_name' => $request->string('name')->toString(),
                'entry_year' => $year ? (int) strtok($year->name, '/') : null,
                'academic_year_id' => $year?->id,
            ]);

            if ($year) {
                $registration = \App\Models\Registration::create([
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                    'status' => \App\Models\Registration::STATUS_DRAFT,
                ]);

                $this->documents->seedPlaceholders($registration);
                $this->completeness->refresh($registration);
            }

            return $user;
        });

        $this->audit->log('auth.registered', $user, 'Pendaftaran akun siswa baru');

        // Log the new account straight in — the user asked to register, not to
        // hunt for a confirmation email in an environment with no mail driver.
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('siswa.wizard', ['step' => 'pribadi'])
            ->with('success', 'Akun berhasil dibuat. Lengkapi data pendaftaran Anda.');
    }
}
