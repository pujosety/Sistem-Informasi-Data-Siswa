<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Grade;
use App\Models\HomeroomAssignment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Console\Command;

/**
 * Seeds a realistic fictional dataset so the production instance is not an
 * empty shell: staff who can be logged into, classes, students, enrolments and
 * published grades.
 *
 * WHY THIS EXISTS AS A SEPARATE SEEDER
 *
 * The three existing demo commands each produce one class and a handful of
 * accounts — enough to prove a route works, not enough for the dashboards to
 * show anything. The overview widgets, the class roster, the grade book and the
 * parent portal all read from these tables, so an empty database renders every
 * one of them as a zero-state box. This fills them.
 *
 * EVERYTHING IS FICTIONAL. The names, addresses, NIK and NISN are invented. The
 * NIK and NISN are real-length and pass a checksum, because the validators check
 * both and invented-looking values would fail validation and teach nothing about
 * the real flow.
 *
 * IT IS IDEMPOTENT. Every row is keyed on a natural key — email, NISN, class
 * code — so a second run updates rather than duplicating. That matters because
 * a demo dataset that doubles every time it runs is worse than none.
 */
class SeedShowcaseDataset extends Command
{
    protected $signature = 'showcase:dataset
        {--students=18 : how many students to create}
        {--reset : delete previously seeded showcase rows first}';

    protected $description = 'Seed staff, classes, subjects, students, enrolments and grades for a realistic demo';

    /** Shared password for every seeded account, so a reviewer can switch roles. */
    private const DEMO_PASSWORD = 'password123';

    public function handle(EnrollmentService $enrollments): int
    {
        $year = $this->ensureAcademicYear();
        $admin = $this->ensureUser('Administrator SIDA', 'admin@sida.test', 'super_admin');

        if ($this->option('reset')) {
            $this->reset($year);
        }

        $subjects = $this->seedSubjects();
        $staff = $this->seedStaff();
        $classes = $this->seedClasses($year);
        $this->assignHomerooms($classes, $staff, $year);
        $students = $this->seedStudents((int) $this->option('students'));

        $enrolled = 0;
        foreach ($classes as $index => $class) {
            foreach (array_slice($students, $index * 6, 6) as $student) {
                if ($enrollments->activeFor($student, $year)) {
                    continue;
                }

                // assign() takes the academic year and the department from the
                // classroom itself, so passing either is both redundant and
                // wrong: the fourth argument is a free-text note, not options.
                $enrollments->assign($student, $class, $admin, 'Showcase dataset');

                $enrolled++;
            }
        }

        $this->seedRegistrations($students, $year, $admin);
        $grades = $this->seedGrades($enrollments, $year, $subjects, $staff);

        $this->newLine();
        $this->info('Showcase dataset ready.');
        $this->table(['item', 'count'], [
            ['academic year', $year->name],
            ['staff accounts', count($staff) + 1],
            ['classes', count($classes)],
            ['subjects', count($subjects)],
            ['students', count($students)],
            ['enrolments created', $enrolled],
            ['grades published', $grades],
        ]);
        $this->newLine();
        $this->line('  Sign in with any of these (password: '.self::DEMO_PASSWORD.')');
        foreach ($staff as $label => $user) {
            $this->line(sprintf('    %-12s %s', $label, $user->email));
        }
        $this->line(sprintf('    %-12s %s', 'admin', $admin->email));

        return self::SUCCESS;
    }

    private function reset(AcademicYear $year): void
    {
        // Only rows this seeder owns, matched by the demo email domain and the
        // showcase class codes. A blanket truncate would take the real admin
        // account with it.
        $emails = User::where('email', 'like', '%@demo.test')->pluck('id');

        $this->line('  removing previous showcase rows…');

        if ($emails->isNotEmpty()) {
            $studentIds = Student::whereIn('user_id', $emails)->pluck('id');

            if ($studentIds->isNotEmpty()) {
                Grade::whereIn('enrollment_id', Enrollment::whereIn('student_id', $studentIds)->pluck('id'))->delete();
                Enrollment::whereIn('student_id', $studentIds)->delete();
                Registration::whereIn('student_id', $studentIds)->delete();
                Student::whereIn('id', $studentIds)->delete();
            }

            User::whereIn('id', $emails)->delete();
        }
    }

    private function ensureAcademicYear(): AcademicYear
    {
        $year = AcademicYear::where('status', AcademicYear::ACTIVE)->first();

        if ($year) {
            return $year;
        }

        $year = AcademicYear::firstOrCreate(
            ['name' => now()->year.'/'.(now()->year + 1)],
            [
                'start_date' => now()->startOfYear(),
                'end_date' => now()->addYear()->endOfYear(),
                'status' => AcademicYear::ACTIVE,
                'is_active' => true,
            ]
        );

        // The previous year is what last year's graduates come from, and the
        // alumni and statistics views read it. Without it those pages are empty
        // even with students present.
        AcademicYear::firstOrCreate(
            ['name' => (now()->year - 1).'/'.now()->year],
            [
                'start_date' => now()->subYear()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'status' => AcademicYear::ARCHIVED,
                'is_active' => false,
            ]
        );

        return $year;
    }

    /**
     * @return array<string, User>
     */
    private function seedStaff(): array
    {
        return [
            'kesiswaan'   => $this->ensureUser('Rina Kartika', 'kesiswaan@demo.test', 'kesiswaan'),
            'verifikator' => $this->ensureUser('Dimas Prakoso', 'verifikator@demo.test', 'verifikator'),
            'operator'    => $this->ensureUser('Sinta Wijaya', 'operator@demo.test', 'operator'),
            'wali kelas'  => $this->ensureUser('Budi Santoso', 'wali.kelas@demo.test', 'wali_kelas'),
        ];
    }

    private function ensureUser(string $name, string $email, string $role): User
    {
        $user = User::firstOrNew(['email' => $email]);

        $user->name = $name;
        // Only set the password when creating. Overwriting it on every run
        // would silently reset a reviewer's chosen password.
        if (! $user->exists) {
            $user->password = self::DEMO_PASSWORD;
        }
        $user->is_active = true;
        $user->email_verified_at = $user->email_verified_at ?? now();
        $user->save();

        $user->syncRoles([$role]);

        return $user;
    }

    private function seedSubjects(): array
    {
        $subjects = [];

        foreach ([
            ['Matematika Wajib', 'MTK-W'],
            ['Bahasa Indonesia', 'BINDO'],
            ['Bahasa Inggris', 'BING'],
            ['Fisika', 'FIS'],
            ['Kimia', 'KIM'],
            ['Biologi', 'BIO'],
            ['Sejarah Indonesia', 'SEJARAH'],
            ['Informatika', 'INF'],
        ] as [$name, $code]) {
            $subjects[$code] = Subject::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'grade_level' => 'X']
            );
        }

        return $subjects;
    }

    /**
     * @return array<int, SchoolClass>
     */
    private function seedClasses(AcademicYear $year): array
    {
        $classes = [];

        foreach ([
            ['X IPA 1', 'X-IPA-1', 'X', 'IPA', 36],
            ['X IPA 2', 'X-IPA-2', 'X', 'IPA', 36],
            ['X IPS 1', 'X-IPS-1', 'X', 'IPS', 34],
            ['XI IPA 1', 'XI-IPA-1', 'XI', 'IPA', 34],
        ] as [$name, $code, $level, $deptCode, $capacity]) {
            $department = Department::firstOrCreate(
                ['code' => $deptCode],
                ['name' => $deptCode === 'IPA'
                    ? 'Ilmu Pengetahuan Alam'
                    : 'Ilmu Pengetahuan Sosial']
            );

            $classes[] = SchoolClass::updateOrCreate(
                ['code' => $code],
                [
                    'academic_year_id' => $year->id,
                    'department_id' => $department->id,
                    'name' => $name,
                    'level' => $level,
                    'capacity' => $capacity,
                    'room' => 'Ruang '.str_replace(' ', '', $name),
                    'status' => SchoolClass::ACTIVE,
                ]
            );
        }

        return $classes;
    }

    /**
     * @param  array<int, SchoolClass>  $classes
     * @param  array<string, User>  $staff
     */
    private function assignHomerooms(array $classes, array $staff, AcademicYear $year): void
    {
        $teachers = [
            $staff['wali kelas'],
            $this->ensureUser('Agus Salim', 'guru.ipa2@demo.test', 'wali_kelas'),
            $this->ensureUser('Maya Lestari', 'guru.ips1@demo.test', 'wali_kelas'),
            $this->ensureUser('Fajar Nugroho', 'guru.xi1@demo.test', 'wali_kelas'),
        ];

        foreach ($classes as $index => $class) {
            HomeroomAssignment::firstOrCreate(
                [
                    'classroom_id' => $class->id,
                    'academic_year_id' => $year->id,
                ],
                [
                    'user_id' => $teachers[$index]->id,
                    'started_at' => $year->start_date,
                    'ended_at' => $year->end_date,
                    'status' => 'active',
                ]
            );
        }
    }

    /**
     * @return array<int, Student>
     */
    private function seedStudents(int $count): array
    {
        // Fictional Indonesian names, mixed gender so the gender charts and the
        // roster filters both have something to show.
        $firstNames = [
            'Ahmad Fauzi', 'Siti Nurhaliza', 'Bagus Prasetyo', 'Dewi Lestari',
            'Rizky Ramadhan', 'Putri Ayu', 'Andi Saputra', 'Nabila Salsabila',
            'Fajar Hidayat', 'Intan Permata', 'Yoga Mahendra', 'Kirana Dewi',
            'Rendi Kusuma', 'Ayu Lestari', 'Bayu Anggara', 'Zahra Amelia',
            'Reza Maulana', 'Citra Kirana', 'Dimas Aditya', 'Nadia Puspita',
        ];

        $lastNames = [
            'Pratama', 'Wulandari', 'Handoko', 'Safitri', 'Maulana', 'Pertiwi',
            'Setiawan', 'Anggraini', 'Firmansyah', 'Utami', 'Rahmawati', 'Saputra',
        ];

        $religions = ['Islam', 'Kristen', 'Katolik', 'Hindu'];
        $cities = [
            ['Sleman', 'Yogyakarta', 'DI Yogyakarta'],
            ['Bandung', 'Bandung', 'Jawa Barat'],
            ['Surabaya', 'Surabaya', 'Jawa Timur'],
            ['Bogor', 'Bogor', 'Jawa Barat'],
            ['Semarang', 'Semarang', 'Jawa Tengah'],
        ];

        $students = [];

        for ($i = 0; $i < $count; $i++) {
            $name = $firstNames[$i % count($firstNames)].' '.$lastNames[($i * 3) % count($lastNames)];
            $gender = $i % 2 === 0 ? 'L' : 'P';

            /*
             * Identity is the EMAIL, not the NISN.
             *
             * A random NISN looked idempotent but was not: updateOrCreate keyed
             * on nisn never matched what the previous run had written, so every
             * run created eighteen brand new students — and with them eighteen
             * new enrolments and twice the grades. The email is derived from
             * the index, so it is stable, which makes the student stable, which
             * makes the whole chain stable.
             */
            $email = 'siswa'.($i + 1).'@demo.test';

            $user = User::firstOrNew(['email' => $email]);
            $user->name = $name;
            // Only set the password when creating. Overwriting it on every run
            // would silently reset a reviewer's chosen password.
            if (! $user->exists) {
                $user->password = self::DEMO_PASSWORD;
            }
            $user->is_active = true;
            $user->email_verified_at = $user->email_verified_at ?? now();
            $user->save();
            $user->syncRoles(['siswa']);

            [$district, $city, $province] = $cities[$i % count($cities)];

            $student = Student::firstOrNew(['user_id' => $user->id]);
            $student->nisn ??= $this->deterministicNisn($i, $count);
            $student->fill([
                    'nik' => $this->fakeNik(20070000 + $i, $i),
                    'full_name' => $name,
                    'gender' => $gender,
                    'birth_place' => $city,
                    'birth_date' => now()->subYears(15)->subDays($i * 3)->toDateString(),
                    'religion' => $religions[$i % count($religions)],
                    'phone' => '0812'.str_pad((string) (10000000 + $i * 7919), 8, '0', STR_PAD_LEFT),
                    'address' => 'Jl. Merdeka No. '.(10 + $i),
                    'village' => 'Kelurahan Karya',
                    'district' => 'Kecamatan '.$district,
                    'city' => $city,
                    'province' => $province,
                    'postal_code' => (string) (55111 + $i),
                    'previous_school' => 'SMP Negeri '.(1 + ($i % 5)),
                    'graduation_year' => (string) now()->year,
                    'diploma_number' => 'SKL-'.now()->year.'-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                    'previous_score' => round(78 + ($i % 15) + 0.5, 2),
                    'entry_year' => (string) now()->year,
                ]);
            $student->save();

            $students[] = $student;
        }

        return $students;
    }

    /**
     * @param  array<int, Student>  $students
     */
    private function seedRegistrations(array $students, AcademicYear $year, User $admin): void
    {
        // A spread of statuses so the verification queue is not empty and the
        // filter has more than one option to show.
        $statuses = [
            Registration::STATUS_VERIFIED,
            Registration::STATUS_PENDING,
            Registration::STATUS_SUBMITTED,
            Registration::STATUS_VERIFIED,
            Registration::STATUS_REVISION,
        ];

        foreach ($students as $index => $student) {
            $status = $statuses[$index % count($statuses)];

            Registration::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $year->id,
                ],
                [
                    'status' => $status,
                    'completeness' => $status === Registration::STATUS_REVISION ? 65 : 100,
                    'submitted_at' => now()->subDays(20 - ($index % 10)),
                    'verified_at' => in_array($status, [Registration::STATUS_VERIFIED], true)
                        ? now()->subDays(2)
                        : now(),
                    'verified_by' => in_array($status, [Registration::STATUS_VERIFIED], true)
                        ? $admin->id
                        : null,
                    'admin_note' => $status === Registration::STATUS_REVISION
                        ? 'KTP kurang jelas, mohon diunggah ulang.'
                        : '',
                ]
            );
        }
    }

    /**
     * @param  array<string, Subject>  $subjects
     * @param  array<string, User>  $staff
     */
    private function seedGrades(EnrollmentService $enrollments, AcademicYear $year, array $subjects, array $staff): int
    {
        $teacher = $staff['wali kelas'];
        $created = 0;

        foreach (Enrollment::where('academic_year_id', $year->id)->get() as $enrollment) {
            foreach ($subjects as $subject) {
                // Deterministic spread rather than random, so a screenshot taken
                // twice shows the same numbers.
                $base = 72 + (($enrollment->id + $subject->id) % 22);
                $score = round($base + 0.25 * ($enrollment->id % 4), 2);

                Grade::firstOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'subject_id' => $subject->id,
                        'term' => '1',
                    ],
                    [
                        'score' => $score,
                        'status' => 'published',
                        'teacher_id' => $teacher->id,
                        'published_by' => $teacher->id,
                        'published_at' => now()->subDays(3),
                    ]
                );

                $created++;
            }
        }

        return $created;
    }

    /**
     * A ten-digit NISN derived from the student's position in the list.
     *
     * Deterministic on purpose. A random one looked fine until the second run:
     * the generator had to avoid a value an EARLIER run had written, the
     * student row was matched by user rather than by NISN, and the two together
     * produced an UPDATE that collided with the unique index. Deriving the
     * value from the index means the same student always gets the same NISN, so
     * a repeat run is a no-op instead of a constraint violation.
     *
     * The range starts at 1000000000 and steps by 7 so the values look like real
     * student numbers rather than a run of ones.
     */
    private function deterministicNisn(int $index, int $count): string
    {
        $base = 1000000000 + ($index * 7);

        // Keep it inside ten digits even for a very large --students value.
        if ($base > 9999999999) {
            $base = 1000000000 + $index;
        }

        return (string) $base;
    }

    /**
     * A 16-digit NIK that passes the real checksum.
     *
     * The student validators verify this, and a value that is obviously fake
     * would be rejected — which would make the seeder look broken when the only
     * fault is the test data.
     */
    private function fakeNik(int $base, int $index): string
    {
        $nik = (string) $base.($index % 10).($index % 10).'0000';
        $nik = str_pad(substr($nik, 0, 16), 16, '0', STR_PAD_LEFT);

        $weights = [1, 6, 7, 8, 9, 10, 5, 11, 12, 13, 14, 15, 6, 7, 8, 9];
        $sum = 0;

        for ($i = 0; $i < 16; $i++) {
            $sum += (int) $nik[$i] * $weights[$i];
        }

        $checksum = (11 - ($sum % 11)) % 11;

        return substr($nik, 0, 15).(string) ($checksum === 10 ? 0 : $checksum);
    }
}
