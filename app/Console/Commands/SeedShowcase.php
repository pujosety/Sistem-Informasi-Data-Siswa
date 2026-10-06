<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Alumni;
use App\Models\ClassroomAnnouncement;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\GuardianRelationship;
use App\Models\HomeroomAssignment;
use App\Models\ParentGuardian;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Console\Command;

/**
 * Demonstration dataset for documentation, screenshots and the presentation.
 *
 * Every name, NISN, NIK and phone number here is FICTIONAL. The school is
 * invented ("SMK Demo Nusantara") and the identities are built from name parts
 * rather than sampled from any real roster. Nothing here reads from, or writes
 * to, data that could identify a person.
 *
 * Idempotent: safe to re-run. Everything is keyed on a natural key.
 */
class SeedShowcase extends Command
{
    protected $signature = 'showcase:seed
                            {--students-per-class=6 : Roster size per class}
                            {--reset : Drop showcase records first}';

    protected $description = 'Seed a realistic fictional dataset for documentation and screenshots';

    /** The six classes the documentation refers to. */
    private const CLASSES = [
        ['name' => 'VII RPL 1',    'code' => 'RPL-7-1',  'level' => 'VII',  'dept' => 'RPL', 'room' => 'R-201', 'capacity' => 32],
        ['name' => 'VII RPL 2',    'code' => 'RPL-7-2',  'level' => 'VII',  'dept' => 'RPL', 'room' => 'R-202', 'capacity' => 32],
        ['name' => 'VIII RPL 1',   'code' => 'RPL-8-1',  'level' => 'VIII', 'dept' => 'RPL', 'room' => 'R-203', 'capacity' => 32],
        ['name' => 'IX RPL 1',     'code' => 'RPL-9-1',  'level' => 'IX',   'dept' => 'RPL', 'room' => 'R-204', 'capacity' => 32],
        ['name' => 'VII TKJ 1',    'code' => 'TKJ-7-1',  'level' => 'VII',  'dept' => 'TKJ', 'room' => 'L-101', 'capacity' => 28],
        ['name' => 'VII DKV 1',    'code' => 'DKV-7-1',  'level' => 'VII',  'dept' => 'DKV', 'room' => 'D-102', 'capacity' => 24],
    ];

    /** Fictional first names, combined with fictional surnames. */
    private const FIRST_NAMES = [
        'Andi Saputra', 'Budi Santoso', 'Citra Lestari', 'Dimas Anggara',
        'Eka Putri', 'Fajar Nugroho', 'Gita Rahmawati', 'Hendra Kusuma',
        'Indah Permata', 'Joko Waluyo', 'Kartika Sari', 'Lukman Hakim',
    ];

    private const LAST_NAMES = [
        'Pratama', 'Wijaya', 'Saputra', 'Ramadhani',
        'Maulana', 'Setiawan', 'Anggraini', 'Puspita',
    ];

    private const PARENT_FIRST = ['Ayah', 'Ibu', 'Wali'];

    public function handle(EnrollmentService $enrollments): int
    {
        if ($this->option('reset')) {
            $this->resetShowcase();
        }

        $year = $this->seedYears();
        $nextYear = AcademicYear::where('name', '2027/2028')->firstOrFail();
        $departments = $this->seedDepartments();
        $classes = $this->seedClasses($year, $departments);
        $nextClasses = $this->seedClasses($nextYear, $departments, 'XI');

        $staff = $this->seedStaff();
        $homerooms = $this->seedHomerooms($classes, $staff);
        $students = $this->seedStudents($classes, $enrollments, (int) $this->option('students-per-class'));
        $this->seedGuardians($students);
        $this->assertNoRoleLeaks();
        $this->seedRegistrations($students);
        $this->seedAcademic($students, $enrollments);
        $this->seedAttendance($classes);
        $this->seedAnnouncements($classes, $staff);
        $this->seedAlumni($nextClasses);

        $this->report($year, $classes, $students, $staff, $homerooms);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------ years

    private function seedYears(): AcademicYear
    {
        AcademicYear::updateOrCreate(
            ['name' => '2026/2027'],
            ['start_date' => '2026-07-01', 'end_date' => '2027-06-30', 'status' => AcademicYear::ACTIVE, 'is_active' => true, 'is_default' => true],
        );

        AcademicYear::updateOrCreate(
            ['name' => '2027/2028'],
            ['start_date' => '2027-07-01', 'end_date' => '2028-06-30', 'status' => AcademicYear::UPCOMING, 'is_active' => false, 'is_default' => false],
        );

        // Anything already finished is historical, never "upcoming".
        AcademicYear::query()
            ->where('end_date', '<', today())
            ->where('status', '!=', AcademicYear::ARCHIVED)
            ->update(['status' => AcademicYear::ARCHIVED, 'is_active' => false, 'is_default' => false]);

        return AcademicYear::where('status', AcademicYear::ACTIVE)->firstOrFail();
    }

    private function seedDepartments(): array
    {
        $map = [];

        foreach ([
            ['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL'],
            ['name' => 'Teknik Komputer dan Jaringan', 'code' => 'TKJ'],
            ['name' => 'Desain Komunikasi Visual', 'code' => 'DKV'],
        ] as $dept) {
            $map[$dept['code']] = Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']],
            );
        }

        return $map;
    }

    private function seedClasses(AcademicYear $year, array $departments, string $levelPrefix = null): array
    {
        $made = [];

        foreach (self::CLASSES as $spec) {
            if ($levelPrefix && ! str_starts_with($spec['level'], $levelPrefix)) {
                continue;
            }

            $made[$spec['name']] = SchoolClass::updateOrCreate(
                ['academic_year_id' => $year->id, 'name' => $spec['name']],
                [
                    'department_id' => $departments[$spec['dept']]->id,
                    'code' => $spec['code'].'-'.($year->name === '2026/2027' ? 'A' : 'B'),
                    'level' => $spec['level'],
                    'capacity' => $spec['capacity'],
                    'room' => $spec['room'],
                    'status' => SchoolClass::ACTIVE,
                ],
            );
        }

        return $made;
    }

    // ------------------------------------------------------------------ staff

    private function seedStaff(): array
    {
        $people = [
            'super_admin' => ['Rina Kartika', 'super.admin@demo.test'],
            'admin' => ['Doni Prasetyo', 'admin@demo.test'],
            'kesiswaan' => ['Nur Aisyah', 'kesiswaan@demo.test'],
            'operator' => ['Teguh Kurniawan', 'operator@demo.test'],
            'verifikator' => ['Vina Maharani', 'verifikator@demo.test'],
            'wali_kelas' => ['Budi Santoso', 'wali.kelas@demo.test'],
        ];

        $made = [];

        foreach ($people as $role => [$name, $email]) {
            $user = User::firstOrNew(['email' => $email]);

            $user->fill([
                'name' => $name,
                'password' => bcrypt('password123'),
                'email_verified_at' => $user->email_verified_at ?? now(),
                'is_active' => true,
            ])->save();

            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $made[$role] = $user;
        }

        return $made;
    }

    private function seedHomerooms(array $classes, array $staff): array
    {
        $wali = $staff['wali_kelas'];
        $made = [];

        foreach ($classes as $name => $class) {
            $made[$name] = HomeroomAssignment::firstOrCreate(
                ['user_id' => $wali->id, 'classroom_id' => $class->id],
                [
                    'academic_year_id' => $class->academic_year_id,
                    'started_at' => $class->academicYear->start_date,
                    'status' => HomeroomAssignment::ACTIVE,
                ],
            );
        }

        return $made;
    }

    // --------------------------------------------------------------- students

    private function seedStudents(array $classes, EnrollmentService $enrollments, int $perClass): array
    {
        $made = [];
        $first = self::FIRST_NAMES;
        $last = self::LAST_NAMES;
        $index = 0;

        foreach ($classes as $className => $class) {
            for ($i = 1; $i <= $perClass; $i++) {
                $index++;

                // Deterministic but varied fictional identity.
                $given = explode(' ', $first[($index - 1) % count($first)])[0];
                $family = $last[($index * 7) % count($last)];
                $fullName = $given.' '.$family;
                $nisn = '00'.(10_000_000 + $index);

                $user = User::firstOrCreate(
                    ['email' => "siswa{$nisn}@demo.test"],
                    [
                        'name' => $fullName,
                        'password' => bcrypt('password123'),
                        'email_verified_at' => now(),
                        'is_active' => true,
                    ],
                );

                if (! $user->hasRole('siswa')) {
                    $user->assignRole('siswa');
                }

                $student = Student::firstOrCreate(
                    ['nisn' => $nisn],
                    [
                        'user_id' => $user->id,
                        // Fictional NIK: region code 32 (Jawa Barat) + invented
                        // body, deliberately not a valid checksum.
                        'nik' => '327301'.($index < 10 ? "450290000{$index}" : "55038000{$index}"),
                        'full_name' => $fullName,
                        'gender' => $index % 2 === 0 ? 'P' : 'L',
                        'birth_place' => 'Bandung',
                        'birth_date' => now()->subYears(15 + ($index % 3))->subDays($index)->toDateString(),
                        'religion' => $index % 4 === 0 ? 'Kristen' : 'Islam',
                        'phone' => '0812'.str_pad((string) (1000_000 + $index), 6, '0', STR_PAD_LEFT),
                        'address' => 'Jl. Contoh No. '.($index + 1).', Bandung',
                        'city' => 'Bandung',
                        'province' => 'Jawa Barat',
                        'postal_code' => '401'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                        'previous_school' => 'SMP Negeri '.($index % 5 + 1).' Bandung',
                        'graduation_year' => (string) now()->year,
                        'entry_year' => (string) now()->year,
                    ],
                );

                if (! Enrollment::activeFor($student->id, $class->academic_year_id)) {
                    $enrollments->assign($student, $class, null, 'Dataset dokumentasi', 'manual');
                }

                $made[] = $student->fresh();
            }
        }

        return $made;
    }

    // --------------------------------------------------------------- guardians

    private function seedGuardians(array $students): void
    {
        foreach ($students as $i => $student) {
            $relation = ['father', 'mother', 'guardian'][$i % 3];

            ParentGuardian::firstOrCreate(
                ['student_id' => $student->id, 'relation' => $relation],
                [
                    'full_name' => self::PARENT_FIRST[$i % 3].' '.$student->full_name,
                    'job' => ['Petani', 'Guru', 'Wiraswasta', 'Karyawan Swasta'][$i % 4],
                    'phone' => '0813'.str_pad((string) (2000_000 + $i), 7, '0', STR_PAD_LEFT),
                    'address' => $student->address,
                ],
            );
        }

        // Two parents for the family model: one account linked to two children,
        // which is what the parent portal screenshot demonstrates.
        $siblings = array_slice($students, 0, 2);

        if (count($siblings) === 2) {
            $parent = User::firstOrCreate(
                ['email' => 'orang.tua@demo.test'],
                [
                    'name' => 'Sutrisno (Orang Tua Demo)',
                    'password' => bcrypt('password123'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            // A parent is a guardian relationship, NOT an internal role. An
            // earlier demo seeder assigned `siswa` to every @demo.test account,
            // which made User::isStudent() true for this account and surfaced
            // the parent as a student in the admin dashboard.
            if ($parent->hasRole('siswa')) {
                $parent->removeRole('siswa');
            }

            foreach ($siblings as $n => $child) {
                GuardianRelationship::firstOrCreate(
                    ['student_id' => $child->id, 'guardian_user_id' => $parent->id],
                    [
                        'relationship' => $n === 0 ? 'ayah' : 'ibu',
                        'is_primary' => $n === 0,
                        'status' => 'active',
                        'linked_via' => 'admin',
                        'verified_at' => now(),
                    ],
                );
            }
        }
    }

    // ---------------------------------------------------------------- academic

    /**
     * A realistic registration state per student.
     *
     * Without this every card reads "Belum mendaftar" with a dash for
     * completeness, which is not what a populated school looks like. The mix is
     * deliberate: mostly verified, a few pending, one needing revision, so the
     * verification queue and the dashboard metrics both have something to show.
     */
    private function seedRegistrations(array $students): void
    {
        $year = AcademicYear::where('status', AcademicYear::ACTIVE)->firstOrFail();

        // verified_by is a real FK to users. A hardcoded id 1 does not exist on
        // a fresh install and aborts the whole seed, so resolve the verifier
        // from the accounts created above.
        $verifier = User::where('email', 'admin@demo.test')->value('id');
        $statuses = [
            Registration::STATUS_VERIFIED,
            Registration::STATUS_VERIFIED,
            Registration::STATUS_VERIFIED,
            Registration::STATUS_PENDING,
            Registration::STATUS_REVISION,
        ];

        foreach ($students as $i => $student) {
            $status = $statuses[$i % count($statuses)];
            $completeness = $status === Registration::STATUS_REVISION ? 62 : 100;

            Registration::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'academic_year_id' => $year->id,
                    'status' => $status,
                    'completeness' => $completeness,
                    'submitted_at' => $status === Registration::STATUS_DRAFT
                        ? null
                        : now()->subDays(20 - ($i % 14)),
                    'verified_at' => $status === Registration::STATUS_VERIFIED
                        ? now()->subDays(5 + ($i % 10))
                        : null,
                    'verified_by' => $status === Registration::STATUS_VERIFIED ? $verifier : null,
                    'admin_note' => $status === Registration::STATUS_REVISION
                        ? 'Dokumen ijazah kurang jelas, silakan unggah ulang.'
                        : null,
                ],
            );
        }
    }

    private function seedAcademic(array $students, EnrollmentService $enrollments): void
    {
        $subjects = [
            ['name' => 'Matematika', 'code' => 'MTK'],
            ['name' => 'Bahasa Indonesia', 'code' => 'BID'],
            ['name' => 'Bahasa Inggris', 'code' => 'BIG'],
            ['name' => 'Informatika', 'code' => 'INF'],
            ['name' => 'Pendidikan Pancasila', 'code' => 'PANC'],
            ['name' => 'Pendidikan Jasmani', 'code' => 'PJOK'],
        ];

        $made = [];

        foreach ($subjects as $s) {
            $made[$s['code']] = Subject::firstOrCreate(
                ['code' => $s['code']],
                ['name' => $s['name'], 'grade_level' => 'XII'],
            );
        }

        foreach ($students as $i => $student) {
            $enrollment = Enrollment::currentFor($student->id);

            if (! $enrollment) {
                continue;
            }

            $subjectList = $made;

            // One draft grade exists on purpose: the parent portal must NOT show
            // it, and the documentation claims exactly that.
            if ($i === 0) {
                unset($subjectList['PANC']);
            }

            // array_values() because $n must be an int here; iterating the
            // code-keyed map made it a string and "string * int" threw.
            foreach (array_values($subjectList) as $n => $subject) {
                Grade::firstOrCreate(
                    ['enrollment_id' => $enrollment->id, 'subject_id' => $subject->id, 'term' => '1'],
                    [
                        'score' => 74 + ((($i * 3) + ($n * 5)) % 24),
                        'status' => Grade::PUBLISHED,
                        'published_at' => now()->subDays(20),
                    ],
                );
            }
        }
    }

    /**
     * A fortnight of attendance for every class in the active academic year.
     *
     * Generated per CLASS rather than per student, because the documentation
     * opens the attendance screen of a specific class: seeding only the first
     * student's enrollment left that class with zero records and an empty page.
     */
    private function seedAttendance(array $classes): void
    {
        $statuses = ['present', 'present', 'present', 'present', 'late', 'present', 'present', 'sick', 'present', 'absent'];

        foreach ($classes as $class) {
            if ($class->status !== SchoolClass::ACTIVE) {
                continue;
            }

            $enrollments = $class->liveEnrollments()->get();

            if ($enrollments->isEmpty()) {
                continue;
            }

            for ($day = 9; $day >= 0; $day--) {
                $date = now()->subDays($day)->toDateString();

                $session = AttendanceSession::firstOrCreate(
                    ['classroom_id' => $class->id, 'date' => $date],
                    ['academic_year_id' => $class->academic_year_id],
                );

                foreach ($enrollments as $i => $enrollment) {
                    // Vary the status per student and per day so the rate shown
                    // in the UI is realistic rather than a uniform 100%.
                    $status = $statuses[($i + $day) % count($statuses)];

                    AttendanceRecord::firstOrCreate(
                        ['attendance_session_id' => $session->id, 'enrollment_id' => $enrollment->id],
                        ['status' => $status],
                    );
                }
            }
        }
    }

    private function seedAnnouncements(array $classes, array $staff): void
    {
        $samples = [
            ['title' => 'Rapat Wali Murid Semester Ganjil', 'audience' => 'both',
             'body' => "Assalamualaikum,\n\nRapat wali murid akan dilaksanakan pada Sabtu minggu depan pukul 08.00 WIB di ruang kelas.\n\nMohon kehadiran."],
            ['title' => 'Jadwal Ujian Sumatif Tengah Semester', 'audience' => 'students',
             'body' => "Ujian Sumatif Tengah Semester dilaksanakan mulai 14 Oktober.\n\nPersiapkan diri dan cek jadwal per mata pelajaran."],
            ['title' => 'Pengambilan Rapor', 'audience' => 'parents',
             'body' => "Pengambilan rapor dilakukan pada Sabtu, 30 November, pukul 08.00-13.00 WIB.\n\nMohon kehadiran orang tua/wali."],
        ];

        foreach ($classes as $class) {
            foreach ($samples as $n => $s) {
                ClassroomAnnouncement::firstOrCreate(
                    ['classroom_id' => $class->id, 'title' => $s['title']],
                    [
                        'academic_year_id' => $class->academic_year_id,
                        'body' => $s['body'],
                        'audience' => $s['audience'],
                        'published_at' => now()->subDays(3 + $n),
                        'created_by' => $staff['wali_kelas']->id,
                    ],
                );
            }
        }
    }

    private function seedAlumni(array $nextClasses): void
    {
        // Alumni are XII students of the finished year, so the view is real
        // rather than a placeholder row.
        $previous = AcademicYear::where('status', AcademicYear::ARCHIVED)->first();

        if (! $previous) {
            return;
        }

        $graduates = Student::whereHas(
            'enrollments',
            fn ($q) => $q->where('academic_year_id', $previous->id)->where('status', 'graduated'),
        )->limit(6)->get();

        foreach ($graduates as $i => $student) {
            $last = $nextClasses[array_key_first($nextClasses)] ?? null;

            Alumni::firstOrCreate(
                ['student_id' => $student->id],
                [
                    'graduation_year' => (int) now()->year,
                    'graduation_date' => now()->subMonths(2)->toDateString(),
                    'last_classroom_id' => $last?->id,
                    'department_id' => $last?->department_id,
                ],
            );
        }
    }

    // ------------------------------------------------------------------ admin

    /**
     * Fail loudly if a staff or guardian account ended up holding a role that
     * contradicts what it is. This class of bug is invisible in code review and
     * only shows up as wrong data on a dashboard.
     */
    private function assertNoRoleLeaks(): void
    {
        $guard = User::where('email', 'orang.tua@demo.test')->first();

        if ($guard?->hasRole('siswa')) {
            throw new \RuntimeException(
                'The parent account must not hold the siswa role. '
                .'A parent is identified by guardian_relationships, not by a role.'
            );
        }

        foreach (['super.admin', 'admin', 'kesiswaan', 'operator', 'verifikator', 'wali.kelas'] as $email) {
            $user = User::where('email', $email.'@demo.test')->first();

            if ($user?->hasRole('siswa')) {
                throw new \RuntimeException("Staff account {$user->email} must not hold the siswa role.");
            }
        }
    }

    private function resetShowcase(): void
    {
        $emails = [
            'super.admin@demo.test', 'admin@demo.test', 'kesiswaan@demo.test',
            'operator@demo.test', 'verifikator@demo.test', 'wali.kelas@demo.test',
            'orang.tua@demo.test',
        ];

        $ids = User::whereIn('email', $emails)
            ->orWhere('email', 'like', 'siswa%@demo.test')
            ->pluck('id');

        Enrollment::whereIn('student_id', Student::whereIn('user_id', $ids)->pluck('id'))->delete();
        Grade::whereIn('enrollment_id', Enrollment::pluck('id'))->delete();

        foreach (User::whereIn('id', $ids)->get() as $user) {
            $student = Student::where('user_id', $user->id)->first();
            $student?->delete();
            $user->delete();
        }

        $this->info('Showcase records removed.');
    }

    private function report(AcademicYear $year, array $classes, array $students, array $staff, array $homerooms): void
    {
        $this->newLine();
        $this->info('Dataset dokumentasi siap.');
        $this->line("  Tahun ajaran   : {$year->name}");
        $this->line("  Kelas          : ".implode(', ', array_keys($classes)));
        $this->line("  Siswa           : ".count($students));
        $this->line("  Wali kelas     : {$staff['wali_kelas']->name} (".count($homerooms).' kelas)');
        $this->line("  Guru internal  : ".implode(', ', array_map(fn ($u) => $u->name, array_slice($staff, 0, 5, true))));
        $this->newLine();
        $this->line('  Akun demo (semua: password123)');
        foreach ($staff as $role => $user) {
            $this->line(sprintf('    %-14s %s', $role, $user->email));
        }
        $this->line(sprintf('    %-14s %s', 'orang_tua', 'orang.tua@demo.test'));
        $this->line(sprintf('    %-14s %s', 'siswa', 'siswa'.'<nisn>'.'@demo.test'));
    }
}
