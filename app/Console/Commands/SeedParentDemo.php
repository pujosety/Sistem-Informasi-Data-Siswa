<?php

namespace App\Console\Commands;

use App\Models\ClassroomAnnouncement;
use App\Models\GuardianRelationship;
use App\Models\Student;
use App\Models\User;
use App\Models\Grade;
use App\Models\Enrollment;
use App\Models\Subject;
use App\Models\AttendanceSession;
use App\Models\AttendanceRecord;
use Illuminate\Console\Command;

/**
 * Development fixture for the parent portal.
 *
 * Creates a guardian LOGIN linked to two students, plus the published grade and
 * attendance the portal is supposed to show. Demonstrates the point that a
 * parent is defined by a relationship, not a role.
 */
class SeedParentDemo extends Command
{
    protected $signature = 'academic:demo-parent {--students=2 : how many children to link}';

    protected $description = 'Seed a parent account linked to students, with published grades and attendance';

    public function handle(): int
    {
        $parent = User::firstOrCreate(
            ['email' => 'orang.tua@demo.test'],
            [
                'name' => 'Sutrisno (Orang Tua)',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $students = Student::whereHas('user', fn ($q) => $q->where('email', 'like', '%@demo.test'))
            ->orderBy('id')
            ->limit(max(1, (int) $this->option('students')))
            ->get();

        if ($students->isEmpty()) {
            $this->error('Tidak ada siswa demo. Jalankan `php artisan academic:demo` lebih dulu.');
            return self::FAILURE;
        }

        $links = 0;

        foreach ($students as $index => $student) {
            $existing = GuardianRelationship::where('guardian_user_id', $parent->id)
                ->where('student_id', $student->id)
                ->exists();

            if (! $existing) {
                GuardianRelationship::create([
                    'student_id' => $student->id,
                    'guardian_user_id' => $parent->id,
                    'relationship' => $index === 0 ? 'ayah' : 'ibu',
                    'is_primary' => $index === 0,
                    'status' => 'active',
                    'linked_via' => 'admin',
                    'verified_at' => now(),
                ]);
                $links++;
            }

            $this->seedAttendance($student);
            $this->seedGrades($student);
        }

        $this->seedAnnouncement($students->first());

        $this->newLine();
        $this->info('Parent demo siap.');
        $this->line("  Akun   : {$parent->email} / password123");
        $this->line("  Anak   : ".$students->pluck('full_name')->implode(', '));
        $this->line("  Link baru: {$links}");

        return self::SUCCESS;
    }

    private function seedAttendance(Student $student): void
    {
        $enrollment = Enrollment::currentFor($student->id);

        if (! $enrollment) {
            return;
        }

        $statuses = ['present', 'present', 'present', 'late', 'present', 'sick', 'present', 'absent', 'present', 'present'];

        foreach (array_slice($statuses, 0, 10) as $offset => $status) {
            $date = now()->subDays(9 - $offset)->toDateString();

            $session = AttendanceSession::firstOrCreate(
                ['classroom_id' => $enrollment->classroom_id, 'date' => $date],
                ['academic_year_id' => $enrollment->academic_year_id],
            );

            AttendanceRecord::firstOrCreate(
                ['attendance_session_id' => $session->id, 'enrollment_id' => $enrollment->id],
                ['status' => $status],
            );
        }
    }

    private function seedGrades(Student $student): void
    {
        $enrollment = Enrollment::currentFor($student->id);

        if (! $enrollment) {
            return;
        }

        // One published and one DRAFT grade: the parent portal must show only
        // the published one, which is exactly what the portal is tested for.
        $subjects = [
            ['name' => 'Matematika', 'code' => 'MTK', 'score' => 87.5, 'status' => Grade::PUBLISHED],
            ['name' => 'Bahasa Indonesia', 'code' => 'BID', 'score' => 92.0, 'status' => Grade::PUBLISHED],
            ['name' => 'Fisika', 'code' => 'FIS', 'score' => 74.0, 'status' => Grade::DRAFT],
        ];

        foreach ($subjects as $data) {
            $subject = Subject::firstOrCreate(
                ['code' => $data['code']],
                ['name' => $data['name'], 'grade_level' => $enrollment->classroom?->level],
            );

            Grade::firstOrCreate(
                ['enrollment_id' => $enrollment->id, 'subject_id' => $subject->id, 'term' => '1'],
                [
                    'score' => $data['score'],
                    'status' => $data['status'],
                    'published_at' => $data['status'] === Grade::PUBLISHED ? now() : null,
                ],
            );
        }
    }

    private function seedAnnouncement(Student $student): void
    {
        $enrollment = Enrollment::currentFor($student->id);

        if (! $enrollment?->classroom) {
            return;
        }

        ClassroomAnnouncement::firstOrCreate(
            ['classroom_id' => $enrollment->classroom_id, 'title' => 'Rapat wali murid semester ini'],
            [
                'academic_year_id' => $enrollment->academic_year_id,
                'body' => "Assalamualaikum,\n\nRapat wali murid akan dilaksanakan pada Sabtu minggu depan pukul 08.00 WIB di ruang kelas.\n\nMohon kehadiran.\n\nTerima kasih.",
                'audience' => 'both',
                'published_at' => now()->subDay(),
            ],
        );
    }
}
