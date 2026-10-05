<?php

namespace App\Console\Commands;

use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\ClassroomAnnouncement;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Post;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\Category;
use Illuminate\Console\Command;

/**
 * Fills the data-driven landing sections so they can be reviewed with content.
 *
 * WHY A DEMO COMMAND AND NOT SEEDER LOGIC IN THE BLOCK
 *
 * The achievements and alumni sections read from the database on purpose — a
 * number a school can see move is a number worth showing, and a number typed
 * into a CMS field is wrong by next year. The consequence is that on a fresh
 * database both sections render nothing, which is CORRECT but makes them
 * impossible to look at.
 *
 * So this creates the graduates that the alumni section needs, and publishes a
 * few posts so the news section has something to show. It is deliberately a
 * command and not part of `showcase:seed`: it writes data that a real school
 * would arrive at through its own academic year, and running it against
 * production would invent graduates.
 *
 * Refuses to run when APP_ENV is production unless --force is passed, because
 * "no alumni yet" is a legitimate state and fabricating some is not a seeding
 * decision to make silently.
 */
class FillLandingDemoCommand extends Command
{
    protected $signature = 'lyfla:landing-demo
                            {--posts=4 : how many news posts to publish}
                            {--graduates=5 : how many students to graduate}
                            {--force : run even in production}';

    protected $description = 'Create graduates and published posts so the landing page can be reviewed with content';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->components->error(
                'Refusing to fabricate graduates on production. Pass --force if you mean it.'
            );

            return self::FAILURE;
        }

        $graduates = $this->graduateStudents((int) $this->option('graduates'));
        $posts = $this->publishPosts((int) $this->option('posts'));

        $this->newLine();
        $this->components->info(sprintf(
            'Landing data ready: %d graduate(s), %d alumni row(s), %d published post(s).',
            $graduates,
            Alumni::query()->count(),
            $posts,
        ));

        return self::SUCCESS;
    }

    /**
     * Move a few students out of the active roster and record them as alumni.
     *
     * The status change is the honest way round: `EnrollmentService` already
     * treats `graduated` as terminal, and writing an `alumni` row for a student
     * who is still actively enrolled would put the two tables out of step —
     * which is exactly the inconsistency `sida:audit-integrity` exists to catch.
     */
    private function graduateStudents(int $count): int
    {
        if ($count < 1) {
            return 0;
        }

        $students = Student::query()
            ->whereHas('enrollments', fn ($q) => $q->where('status', 'active'))
            ->with('enrollments')
            ->limit($count)
            ->get();

        if ($students->isEmpty()) {
            $this->components->warn('No actively enrolled students to graduate.');

            return 0;
        }

        $department = Department::query()->first();

        foreach ($students as $index => $student) {
            foreach ($student->enrollments as $enrollment) {
                if ($enrollment->status === 'active') {
                    $enrollment->update(['status' => 'graduated']);
                }
            }

            $lastClass = SchoolClass::query()->orderByDesc('id')->first();

            Alumni::firstOrCreate(
                ['student_id' => $student->id],
                [
                    'graduation_year' => (int) now()->year,
                    'graduation_date' => now()->subMonths(2)->toDateString(),
                    'last_classroom_id' => $lastClass?->id,
                    'department_id' => $lastClass?->department_id ?? $department?->id,
                ],
            );
        }

        return $students->count();
    }

    /**
     * Publish posts so the news block has a lead and three supporting cards.
     *
     * @return int
     */
    private function publishPosts(int $count): int
    {
        if ($count < 1) {
            return 0;
        }

        $author = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first()
            ?? User::query()->first();

        $category = Category::query()->firstOrCreate(
            ['slug' => 'kegiatan'],
            ['name' => 'Kegiatan', 'description' => 'Kegiatan dan jejak sekolah'],
        );

        // Existing drafts and pending posts become public rather than being
        // duplicated: a school that has written three articles and lost them to
        // a filter bug should not find four new ones after running a demo.
        $existing = Post::query()
            ->whereIn('status', [Post::DRAFT, Post::PENDING, Post::SCHEDULED])
            ->limit($count)
            ->get();

        foreach ($existing as $post) {
            $post->update([
                'status' => Post::PUBLISHED,
                'is_public' => true,
                'published_at' => $post->published_at ?? now()->subDays($post->id % 30),
                'author_id' => $post->author_id ?? $author?->id,
                'category_id' => $post->category_id ?? $category->id,
            ]);
        }

        $remaining = $count - $existing->count();

        if ($remaining > 0) {
            $headlines = [
                ['Penerimaan Peserta Didik Baru 2026/2027 Resmi Dibuka', 'Sekolah membuka pendaftaran peserta didik baru untuk tahun ajaran 2026/2027. Pendaftaran dilakukan secara online dan berkas diverifikasi oleh tim admisi.'],
                ['Tim Sains Raih Juara Tingkat Provinsi', 'Tim sains sekolah meraih medali pada lomba tingkat provinsi cabang mata pelajaran yang diikuti sekolah-sekolah dari seluruh daerah.'],
                ['Ekstrakurikuler Robotika Tambah Kelas Baru', 'Minat siswa terhadap robotika terus meningkat. Kelas baru dibuka untuk siswa kelas X dan XI dengan pembimbing dari alumni Teknik Elektro.'],
                ['Kegiatan Literasi Digital untuk Orang Tua', 'Sekolah mengadakan kegiatan literasi digital bagi orang tua guna mendampingi anak belajar di rumah.'],
            ];

            foreach (array_slice($headlines, 0, $remaining) as $index => [$title, $body]) {
                Post::firstOrCreate(
                    ['slug' => \Illuminate\Support\Str::slug($title)],
                    [
                        'kind' => Post::KIND_POST,
                        'title' => $title,
                        'body' => $body,
                        'excerpt' => \Illuminate\Support\Str::limit($body, 140),
                        'status' => Post::PUBLISHED,
                        'is_public' => true,
                        'published_at' => now()->subDays($index * 4),
                        'author_id' => $author?->id,
                        'category_id' => $category->id,
                    ],
                );
            }
        }

        return Post::query()->publishedAndPublic()->count();
    }
}