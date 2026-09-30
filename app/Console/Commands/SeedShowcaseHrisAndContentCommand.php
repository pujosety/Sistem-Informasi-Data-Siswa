<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Employee;
use App\Models\Post;
use App\Models\User;
use App\Services\CmsPostService;
use Illuminate\Console\Command;

/**
 * The last two demo gaps: HRIS records and CMS content.
 *
 * Both are empty after `showcase:dataset`, and both are empty for the same
 * reason — Phase 3 built the tables and then, deliberately, refused to guess
 * anything about real people. `employees` was not backfilled because a
 * `kesiswaan` account might be a teacher, a clerk or a vice principal and only
 * the school knows. `cms_posts` was empty because nothing had been written.
 *
 * That decision is right for real data and wrong for a demo, so this command
 * makes the guess EXPLICIT and scoped: it records an employment for the demo
 * staff accounts, using the role each one already holds, and publishes two
 * articles. Every row it writes is keyed on a demo account or a demo slug, so
 * `showcase:dataset --reset` still removes all of it.
 *
 * It does not invent a payroll ledger, a leave book or a contract. Those are
 * the parts of HRIS that are genuinely unbuilt, and a demo that showed them
 * would be showing a fiction about the product rather than about the school.
 */
class SeedShowcaseHrisAndContentCommand extends Command
{
    protected $signature = 'showcase:hris-content {--dry-run : Report and stop}';

    protected $description = 'Record demo employment and publish two demo articles';

    /** role => [position, department, employment_type] */
    private const STAFF = [
        'wali_kelas' => ['Guru Informatika', 'Rekayasa Perangkat Lunak', 'permanent'],
        'kesiswaan' => ['Staf Tata Usaha', 'Administrasi', 'permanent'],
        'verifikator' => ['Petugas Verifikasi', 'Administrasi', 'contract'],
        'operator' => ['Operator Pendaftaran', 'Administrasi', 'contract'],
    ];

    public function handle(CmsPostService $posts): int
    {
        $dry = $this->option('dry-run');

        $this->line('Employment records');
        $this->line(sprintf('  %d exist', Employee::count()));

        if (! $dry) {
            $this->recordEmployment();
        }

        $this->line(sprintf('  %d after', Employee::count()));

        $this->newLine();
        $this->line('Articles');
        $this->line(sprintf('  %d exist', Post::query()->count()));

        if (! $dry) {
            $this->writeContent($posts);
        }

        $this->line(sprintf('  %d after', Post::query()->count()));

        if ($dry) {
            $this->newLine();
            $this->comment('Dry run.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['item', 'count'], [
            ['employees', Employee::count()],
            ['published articles', Post::query()->where('status', Post::PUBLISHED)->count()],
        ]);

        return self::SUCCESS;
    }

    /**
     * One employment per demo staff account, keyed on the account itself.
     *
     * Keyed on `user_id` rather than created blindly, because running this
     * twice must not give one teacher two contracts — the exact failure a
     * nullable `user_id` invites.
     */
    private function recordEmployment(): void
    {
        $number = 0;

        foreach (self::STAFF as $role => [$position, $department, $type]) {
            $user = User::where('email', 'like', '%@demo.test')
                ->whereHas('roles', fn ($q) => $q->where('name', $role))
                ->first();

            if (! $user || Employee::where('user_id', $user->id)->exists()) {
                continue;
            }

            Employee::create([
                'user_id' => $user->id,
                'employee_number' => 'PG-'.(2026).'-'.str_pad((string) ++$number, 3, '0', STR_PAD_LEFT),
                'department_id' => $this->department($department)->id,
                'position' => $position,
                'employment_status' => Employee::ACTIVE,
                'employment_type' => $type,
                'hire_date' => now()->subYears(2)->toDateString(),
                'created_by' => $user->id,
            ]);
        }
    }

    /**
     * A department, created if absent.
     *
     * `code` is NOT NULL with no default, so a firstOrCreate keyed on the name
     * alone fails the insert with a raw 1364 — the same trap the gradebook
     * fixture fell into.
     */
    private function department(string $name): \App\Models\Department
    {
        return \App\Models\Department::firstOrCreate(
            ['name' => $name],
            ['code' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 5))]
        );
    }

    /**
     * Two articles, one of them published.
     *
     * Published through `CmsPostService::publish()` rather than by writing the
     * status column, because the service is what enforces that publishing is a
     * separate permissioned act — a seeder that set `status` directly would
     * produce content the public query can never see, which is a demo that
     * looks like a broken public site.
     */
    private function writeContent(CmsPostService $posts): void
    {
        // The AUTHOR and the PUBLISHER are different accounts, and the split is
        // the point: `kesiswaan` holds cms.posts.create and cms.posts.edit and
        // deliberately NOT cms.posts.publish, so a school can have a teacher
        // write the news and a head approve it. CmsPostService::publish()
        // re-checks the permission itself and refused this seeder until the
        // actor was the one that actually holds it — which is the guard doing
        // its job, not a bug to work around.
        $author = User::where('email', 'kesiswaan@demo.test')->first();
        $publisher = User::where('email', 'admin@sida.test')->first()
            ?? User::where('email', 'like', '%@demo.test')->first();

        if (! $author || ! $publisher) {
            return;
        }

        $category = Category::firstOrCreate(
            ['name' => 'Pengumuman'],
            ['slug' => 'pengumuman'],
        );

        $articles = [
            [
                'title' => 'Penerimaan Peserta Didik Baru Tahun 2026/2027',
                'body' => '<p>Pendaftaran peserta didik baru dibuka mulai 1 Juni. '
                    .'Calon peserta didik dapat mendaftar melalui portal sekolah atau '
                    .'langsung ke ruang tata usaha pada jam kerja.</p>',
                'publish' => true,
            ],
            [
                'title' => 'Jadwal Ujian Tengah Semester Ganjil',
                'body' => '<p>Ujian tengah semester dilaksanakan mulai 15 Juli. '
                    .'Peserta didik diminta hadir 15 menit lebih awal dan '
                    .'membawa kartu peserta ujian.</p>',
                'publish' => false,
            ],
        ];

        foreach ($articles as $article) {
            $post = Post::where('slug', \Illuminate\Support\Str::slug($article['title']))->first();

            if (! $post) {
                $post = $posts->create($author, [
                    'kind' => Post::KIND_POST,
                    'title' => $article['title'],
                    'body' => $article['body'],
                    'category_id' => $category->id,
                ]);
            }

            // Publication is decided on EVERY run, not only when the post is
            // first created. An earlier version skipped the whole entry when
            // the post already existed, so a run that created it and then
            // failed to publish it left a draft behind FOREVER — the seeder
            // reported success and the article stayed invisible.
            if ($article['publish'] && ! $post->is_public) {
                $posts->publish($post, $publisher);
            }
        }
    }
}
