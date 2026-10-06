<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Department;
use App\Models\Post;
use App\Models\Subject;
use App\Models\User;
use App\Services\CmsPostService;
use App\Services\SettingsService;
use Illuminate\Console\Command;

/**
 * Fills the public school website, so it is a website rather than a shell.
 *
 * THE PROBLEM
 *
 * The public site renders, and it renders EMPTY. Zero students, no school
 * name, no headmaster, no address, one lonely article. A visitor sees a
 * template with nothing in it, which is indistinguishable from a broken
 * deployment — and the whole point of a public site is that a stranger can
 * look at it.
 *
 * WHY SETTINGS AND NOT A MIGRATION
 *
 * `school.*` lives in the `settings` table, not in a migration, and
 * SettingsService::seedDefaults() only creates a key if it is absent. That is
 * correct — it means an operator's edit is never overwritten by a deploy — and
 * it also means editing a seed list does nothing here. So this command uses
 * `setMany()`, which writes only the keys it is given, and it never calls
 * seedDefaults().
 *
 * THE HEADMASTER
 *
 * `school.headmaster` is a SETTING, not a user account. It is a name printed
 * on a public page, and it is deliberately not an employee record: a head
 * signs a letter, but they do not log in, and giving the site a login for them
 * would be inventing an account nobody asked for. The fictional name lives
 * only in that one setting.
 *
 * EVERYTHING HERE IS FICTIONAL and is scoped to demo accounts and demo slugs,
 * so `showcase:dataset --reset` still removes the articles. The settings are
 * NOT removed by a reset, because a school would have typed its own identity
 * there and a demo must not delete it.
 */
class SeedPublicSiteCommand extends Command
{
    protected $signature = 'showcase:public-site
        {--dry-run : Report what would change and stop}
        {--reset-identity : Put the placeholder school identity back}';

    protected $description = 'Fill the public school website: identity, figures and articles';

    public function handle(SettingsService $settings, CmsPostService $posts): int
    {
        $dry = $this->option('dry-run');
        $reset = $this->option('reset-identity');

        $this->fillIdentity($settings, $reset, $dry);
        $this->fillFigures();
        $this->fillProgrammes($dry);
        $this->writeArticles($posts, $dry);

        $this->newLine();

        if ($dry) {
            $this->comment('Dry run. Re-run without --dry-run to write.');

            return self::SUCCESS;
        }

        $this->info('Public site ready.');
        $this->table(['item', 'value'], [
            ['school name', (string) $settings->get('school.name')],
            ['headmaster', (string) $settings->get('school.headmaster')],
            ['articles published', Post::query()->where('status', Post::PUBLISHED)->count()],
            ['articles drafted', Post::query()->where('status', Post::DRAFT)->count()],
            ['departments', Department::count()],
            ['subjects', Subject::count()],
        ]);

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- identity

    /**
     * The published identity of the school.
     *
     * Only keys that are EMPTY are written, unless --reset-identity says
     * otherwise. An operator who has typed the real name of their school must
     * not have it replaced by a fictional one because a demo was re-run — the
     * demo is the thing that is disposable here, not the school's own name.
     */
    private function fillIdentity(SettingsService $settings, bool $reset, bool $dry): void
    {
        $identity = [
            'app.name' => 'Sistem Informasi Data Siswa',
            'app.short_name' => 'SIDA',
            'app.tagline' => 'Pendaftaran, akademik, dan informasi sekolah dalam satu portal.',
            'school.name' => 'SMP 1 LYFLA',
            'school.npsn' => '20219876',
            'school.address' => 'Jl. Pendidikan No. 17',
            'school.city' => 'Bandung',
            'school.district' => 'Coblong',
            'school.postal_code' => '40132',
            'school.province' => 'Jawa Barat',
            'school.email' => 'info@smpn1.sch.id',
            'school.phone' => '(022) 720-1234',
            'school.website' => 'https://smpn1.sch.id',
            // The fictional headmaster. A setting, not an account — see the
            // class docblock.
            'school.headmaster' => 'Lucky Noor Fadilla',
        ];

        $toWrite = [];

        foreach ($identity as $key => $value) {
            $current = $settings->get($key);

            if (! $reset && filled($current) && $current !== $value) {
                // Someone has already set this. Leave it alone and say so.
                $this->line(sprintf('  %-22s kept (already set)', $key));

                continue;
            }

            $toWrite[$key] = $value;
        }

        if ($toWrite !== [] && ! $dry) {
            $settings->setMany($toWrite);

            /*
             * setMany() -> set() -> flush() -> Cache::forget(), which forgets
             * nothing when this process was started with a different cache
             * store than the web container uses — and the deployment script
             * deliberately runs artisan with CACHE_STORE=array.
             *
             * So the web container kept serving a forever-cache entry built
             * before this seeder ran, and /tentang rendered the school NAME
             * (which has a DEFAULTS fallback) with NPSN, address and headmaster
             * blank. The row is deleted in SQL so the invalidation does not
             * depend on which store is configured here.
             */
            $this->purgeDatabaseCache();
        }

        $this->line(sprintf(
            'identity: %d setting(s) %s',
            count($toWrite),
            $dry ? 'would be written' : 'written'
        ));
    }

    /**
     * Delete the application's cache rows directly, whatever store this
     * process is configured with.
     *
     * Names only; the values are serialised payloads.
     */
    private function purgeDatabaseCache(): int
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('cache')) {
                return 0;
            }

            return \Illuminate\Support\Facades\DB::table('cache')
                ->where('key', 'like', 'laravel-cache-%')
                ->delete();
        } catch (\Throwable $e) {
            $this->warn('  could not purge the cache: '.$e->getMessage());

            return 0;
        }
    }

    /**
     * The counts on the home page, reported so an empty one is explainable.
     *
     * These are aggregates over the demo data. A school with real students
     * shows its own numbers here for free — no seeding involved.
     */
    private function fillFigures(): void
    {
        $this->line('figures:');
        $this->line(sprintf('  students %d · classes %d · subjects %d',
            \App\Models\Student::count(),
            \App\Models\SchoolClass::query()->where('status', \App\Models\SchoolClass::ACTIVE)->count(),
            Subject::count()
        ));
    }

    /**
     * Departments, with a subject each, so /program is not an empty list.
     *
     * Keyed on the name, so re-running neither duplicates nor renames.
     */
    private function fillProgrammes(bool $dry): void
    {
        if ($dry) {
            return;
        }

        $programmes = [
            ['Ilmu Alam', 'Mata pelajaran ilmu alam', ['Matematika', 'Fisika', 'Kimia', 'Biologi']],
            ['Ilmu Sosial', 'Mata pelajaran ilmu sosial', ['Ekonomi', 'Sosiologi', 'Geografi', 'Sejarah']],
            ['Bahasa', 'Mata pelajaran bahasa', ['Bahasa Indonesia', 'Bahasa Inggris']],
        ];

        $created = 0;

        foreach ($programmes as [$department, $description, $subjects]) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $department), 0, 5));

            // Same reason as the subjects above: `code` is the unique index.
            Department::firstOrCreate(
                ['code' => $code],
                ['name' => $department, 'description' => $description]
            );

            foreach ($subjects as $subject) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $subject), 0, 8));

                /*
                 * Looked up by CODE as well as name, not name alone.
                 *
                 * `code` carries a unique index and `name` does not, so
                 * firstOrCreate(['name' => 'Sejarah']) finds nothing when a
                 * "Sejarah" already exists under a different code, and the
                 * insert then dies on the code index. The name is what the
                 * seeder means, but the index is what the database enforces,
                 * and both have to be consulted before writing.
                 */
                if (Subject::where('name', $subject)->orWhere('code', $code)->exists()) {
                    $created++;

                    continue;
                }

                Subject::create(['name' => $subject, 'code' => $code]);

                $created++;
            }

            $this->line(sprintf('  %-14s %s', $department, $description));
        }

        $this->line(sprintf('programmes: %d department(s), %d subject name(s)', count($programmes), $created));
    }

    /**
     * The articles the public news page lists.
     *
     * Published through the service, which sets `is_public` alongside the
     * status. Writing the status directly would produce articles the public
     * query can never return, and a news page that looks broken.
     */
    private function writeArticles(CmsPostService $posts, bool $dry): void
    {
        $writer = User::where('email', 'kesiswaan@demo.test')->first();
        $publisher = User::where('email', 'admin@sida.test')->first();

        if (! $writer || ! $publisher) {
            $this->warn('  no demo accounts — run showcase:dataset first');

            return;
        }

        $category = Category::firstOrCreate(
            ['name' => 'Pengumuman'],
            ['slug' => 'pengumuman'],
        );

        $articles = [
            [
                'title' => 'Selamat Datang di SMP 1 LYFLA',
                'body' => '<p>SMP 1 LYFLA mulai beroperasi pada tahun ajaran 2026/2027. '
                    .'Seluruh administrasi — pendaftaran, verifikasi berkas, pencatatan '
                    .'akademik hingga laporan — berjalan pada satu portal.</p>'
                    .'<p>Kepala sekolah, <strong>Lucky Noor Fadilla</strong>, menyampaikan '
                    .'ucapan selamat datang kepada seluruh peserta didik.</p>',
                'publish' => true,
            ],
            [
                'title' => 'Penerimaan Peserta Didik Baru Tahun 2026/2027',
                'body' => '<p>Pendaftaran peserta didik baru dibuka mulai 1 Juni hingga 30 Juni. '
                    .'Calon peserta didik dapat mendaftar melalui portal sekolah atau '
                    .'langsung ke ruang tata usaha pada jam kerja.</p>'
                    .'<p>Berkas yang disiapkan: akta kelahiran, kartu keluarga, '
                    .'ijazah atau SKL, dan pas foto.</p>',
                'publish' => true,
            ],
            [
                'title' => 'Jadwal Ujian Tengah Semester Ganjil',
                'body' => '<p>Ujian tengah semester dilaksanakan mulai 15 Juli. '
                    .'Peserta didik diminta hadir 15 menit lebih awal dan '
                    .'membawa kartu peserta ujian.</p>',
                'publish' => true,
            ],
            [
                'title' => 'Prestasi Siswa Kelas XII di Olimpiade Sains',
                'body' => '<p>Tim olimpiade sains sekolah meraih medali emas pada tingkat '
                    .'kabupaten. Tim terdiri atas tiga siswa kelas XII dan dibimbing '
                    .'dua guru.</p>',
                'publish' => true,
            ],
            [
                'title' => 'Pengingat Pengisian Rapor Semester Genap',
                'body' => '<p>Guru diminta menyelesaikan pengisian nilai paling lambat '
                    .'20 Juli. Nilai yang belum terbit tidak akan tampil pada rapor '
                    .'orang tua.</p>',
                'publish' => false,
            ],
        ];

        $written = 0;
        $published = 0;

        foreach ($articles as $article) {
            $slug = \Illuminate\Support\Str::slug($article['title']);
            $post = Post::where('slug', $slug)->first();

            if (! $post) {
                if ($dry) {
                    continue;
                }

                $post = $posts->create($writer, [
                    'kind' => Post::KIND_POST,
                    'title' => $article['title'],
                    'body' => $article['body'],
                    'category_id' => $category->id,
                ]);

                $written++;
            }

            // Publication is decided on every run, not only on creation — a
            // post that was created and then failed to publish would otherwise
            // stay a draft forever while the seeder reported success.
            if ($article['publish'] && ! $post->is_public && ! $dry) {
                $posts->publish($post, $publisher);
                $published++;
            }
        }

        $this->line(sprintf(
            'articles: %d new, %d newly published, %d published in total',
            $written,
            $published,
            Post::query()->where('status', Post::PUBLISHED)->count()
        ));
    }
}
