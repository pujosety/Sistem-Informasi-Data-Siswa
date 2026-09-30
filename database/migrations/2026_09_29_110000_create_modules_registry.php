<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The module registry: which parts of the platform are switched on.
 *
 * WHY THIS IS A TABLE AND NOT A CONFIG FILE
 *
 * §50 requires that a disabled module leaves no broken navigation behind, and
 * that the answer differs per deployment. A config file would work for the flag
 * but not for the "which modules exist for this school" question, and it would
 * still need a seed, which is the same work with a worse home.
 *
 * WHY THERE IS NO `enabled` COLUMN ON EVERY FEATURE TABLE
 *
 * Because a module is a section, not a table. Switching off "lms" must not mean
 * writing to seventeen tables, and it must not mean a deployment where a
 * half-applied toggle leaves some tables behind and others gone.
 *
 * `is_required` distinguishes the two kinds. Required modules are the platform:
 * turning one off would leave the application with no way to record a student,
 * so the toggle is ignored for them. Optional modules are genuinely optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();

            // Stable identifier used in code and in navigation items. Not the
            // name, which is display text and may be translated.
            $table->string('key', 40)->unique();

            $table->string('name', 100);
            $table->string('description', 255)->nullable();

            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_required')->default(false);

            /*
             * A required module cannot be disabled. Enforced in the service
             * layer rather than by a database constraint, because a CHECK
             * expression cannot reference the row being inserted and MySQL
             * ignores CHECK entirely in most versions.
             */
            $table->string('route_prefix', 60)->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'sort_order'], 'modules_enabled_order_index');
        });

        $this->seed();
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }

    /**
     * The platform's own module list.
     *
     * The first eight are the modules that already exist in this codebase —
     * the registry records them so a deployment can be described completely
     * from day one, rather than a module appearing only when it is written.
     *
     * The last four are placeholders with no implementation. They are seeded
     * disabled so a navigation filter that consults the registry does not
     * surface a section that leads nowhere. Enabling one without implementing
     * it is the failure this prevents.
     */
    private function seed(): void
    {
        $now = now();

        $modules = [
            // key            name                  required  enabled  prefix
            ['students', 'Data Siswa', true, true, '/kesiswaan', 10],
            ['academic', 'Akademik', true, true, '/akademik', 20],
            ['documents', 'Dokumen', true, true, null, 30],
            ['attendance', 'Absensi', true, true, null, 40],
            // Not required. Admission is a process with a season, and a school
            // that has closed intake for the year needs to be able to hide the
            // section — `required` is reserved for what the platform cannot
            // function without.
            ['ppdb', 'PPDB', false, true, '/daftar', 50],
            ['parent', 'Portal Orang Tua', false, true, '/orang-tua', 60],

            // Seeded present but disabled: registered for the platform's
            // completeness, switched on when the module is built.
            ['cms', 'Website & CMS', false, false, '/cms', 70],
            ['lms', 'E-Learning', false, false, '/lms', 80],
            ['hris', 'Kepegawaian', false, false, '/erp', 90],
            ['assets', 'Inventaris', false, false, null, 100],

            // Named in §50 as future modules. Disabled, with no route prefix,
            // because there is nothing to link to.
            ['payroll', 'Payroll', false, false, null, 110],
            ['finance', 'Keuangan', false, false, null, 120],
            ['procurement', 'Pengadaan', false, false, null, 130],
        ];

        foreach ($modules as [$key, $name, $required, $enabled, $prefix, $order]) {
            // insertOrIgnore so re-running the migration, or a second deploy
            // that re-seeds, cannot fail on the unique key — and cannot
            // silently reset an operator's toggle back to its default either.
            DB::table('modules')->insertOrIgnore([
                'key' => $key,
                'name' => $name,
                'is_enabled' => $enabled,
                'is_required' => $required,
                'route_prefix' => $prefix,
                'sort_order' => $order,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
