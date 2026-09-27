<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\HomeroomAssignment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ClassScope;
use App\Services\EnrollmentService;
use Illuminate\Console\Command;

/**
 * Development fixture for the academic lifecycle.
 *
 * Creates a Wali Kelas, a next academic year, and enough students to exercise
 * rosters, attendance and promotion. Safe to re-run: everything is
 * firstOrCreate keyed on a natural key.
 */
class SeedAcademicDemo extends Command
{
    protected $signature = 'academic:demo
                            {--students=8 : How many students to create}
                            {--fresh-names : Append a run marker so a second run makes new students}';

    protected $description = 'Seed a Wali Kelas, a second academic year, and students for classroom testing';

    public function handle(EnrollmentService $enrollments, ClassScope $scope): int
    {
        $this->seedAcademicYears();
        $this->seedHomeroomTeacher();
        $this->backfillDemoStudentRoles();
        $this->seedVerifier();

        $year = AcademicYear::where('status', AcademicYear::ACTIVE)->firstOrFail();
        $nextYear = AcademicYear::where('status', AcademicYear::UPCOMING)->firstOrFail();
        $class = $this->seedClass($year);
        $nextClass = $this->seedClass($nextYear, 'XI IPA 1', 'XI');

        $count = (int) $this->option('students');
        $created = 0;

        for ($i = 1; $i <= $count; $i++) {
            $name = 'Siswa Demo '.$i;
            $nisn = '00'.(9000000 + $i).'1';

            $student = Student::firstOrCreate(
                ['nisn' => $nisn],
                [
                    'user_id' => $this->ensureUser('Siswa'.$i.' Demo', $nisn.'@demo.test')->id,
                    'full_name' => $name,
                    'gender' => $i % 2 === 0 ? 'P' : 'L',
                    'birth_place' => 'Bandung',
                    'birth_date' => now()->subYears(16)->addDays($i)->toDateString(),
                ],
            );

            if (! Enrollment::activeFor($student->id, $year->id)) {
                $enrollments->assign($student, $class, null, 'Seed demo', 'manual');
                $created++;
            }
        }

        $this->newLine();
        $this->info('Academic demo siap.');
        $this->line("  Tahun ajaran aktif    : {$year->name}");
        $this->line("  Tahun ajaran mendatang: {$nextYear->name}");
        $this->line("  Kelas aktif           : {$class->name} ({$class->id})");
        $this->line("  Kelas tahun depan     : {$nextClass->name} ({$nextClass->id})");
        $this->line("  Siswa baru ditempatkan: {$created}");

        $teacher = User::where('email', 'wali.kelas@demo.test')->first();
        if ($teacher) {
            $this->line("  Wali kelas            : {$teacher->name} \\u{1F449} /akademik/kelas/{$class->id}");
        }

        return self::SUCCESS;
    }

    /**
     * A verifier account, so the dedicated verification workspace has live
     * coverage. Without it that tier could only be tested through admin, which
     * also holds verification.approve and therefore hides the boundary.
     */
    private function seedVerifier(): void
    {
        $verifier = User::where('email', 'verifikator@demo.test')->first();

        if (! $verifier) {
            $verifier = User::create([
                'name' => 'Verifikator Demo',
                'email' => 'verifikator@demo.test',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        if (! $verifier->hasRole('verifikator')) {
            $verifier->assignRole('verifikator');
        }
    }

    /** Give any previously seeded demo student the `siswa` role. */
    private function backfillDemoStudentRoles(): void
    {
        $users = User::where('email', 'like', '%@demo.test')
            ->where('email', '!=', 'wali.kelas@demo.test')
            ->whereDoesntHave('roles')
            ->get();

        foreach ($users as $user) {
            $user->assignRole('siswa');
        }
    }

    private function seedAcademicYears(): void
    {
        AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => AcademicYear::ACTIVE, 'is_active' => true, 'is_default' => true],
        );

        AcademicYear::updateOrCreate(
            ['name' => '2027/2028'],
            ['start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => AcademicYear::UPCOMING, 'is_active' => false, 'is_default' => false],
        );

        // Anything whose end date has passed is historical. Without this the
        // old 2025/2026 row was still flagged "upcoming" and got picked as the
        // next school year, which is how a promotion would land in the past.
        AcademicYear::query()
            ->where('end_date', '<', today())
            ->where('status', '!=', AcademicYear::ARCHIVED)
            ->update(['status' => AcademicYear::ARCHIVED, 'is_active' => false, 'is_default' => false]);

        // Exactly one active + default year.
        AcademicYear::query()
            ->where('name', '!=', '2026/2027')
            ->where('status', AcademicYear::ACTIVE)
            ->update(['status' => AcademicYear::UPCOMING, 'is_active' => false, 'is_default' => false]);
    }

    private function seedHomeroomTeacher(): void
    {
        $teacher = User::where('email', 'wali.kelas@demo.test')->first();

        if (! $teacher) {
            $teacher = User::create([
                'name' => 'Budi Santoso (Wali Kelas)',
                'email' => 'wali.kelas@demo.test',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        if (! $teacher->hasRole('wali_kelas')) {
            $teacher->assignRole('wali_kelas');
        }

        $class = SchoolClass::where('name', 'X IPA 1')->first();

        if ($class && ! HomeroomAssignment::where('user_id', $teacher->id)->where('classroom_id', $class->id)->exists()) {
            HomeroomAssignment::create([
                'user_id' => $teacher->id,
                'classroom_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'started_at' => now()->toDateString(),
                'status' => HomeroomAssignment::ACTIVE,
            ]);
        }
    }

    private function seedClass(AcademicYear $year, string $name = 'X IPA 1', string $level = 'X'): SchoolClass
    {
        $department = Department::where('code', 'IPA')->first()
            ?? Department::create(['name' => 'Ilmu Pengetahuan Alam', 'code' => 'IPA']);

        return SchoolClass::updateOrCreate(
            ['academic_year_id' => $year->id, 'name' => $name],
            [
                'department_id' => $department->id,
                'level' => $level,
                'capacity' => 36,
                'room' => 'R-201',
                'status' => SchoolClass::ACTIVE,
            ],
        );
    }

    /**
     * Demo students must carry the `siswa` role, otherwise they cannot open the
     * student portal at all and a smoke test that logs in as one silently tests
     * a guest.
     */
    private function ensureUser(string $name, string $email): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        if (! $user->hasRole('siswa')) {
            $user->assignRole('siswa');
        }

        return $user;
    }
}
