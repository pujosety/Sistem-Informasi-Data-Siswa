<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * MySQL sql_mode=only_full_group_by compatibility (the production 500).
 *
 * The deployed MySQL runs with ONLY_FULL_GROUP_BY, which rejects a grouped
 * query whose SELECT list references a non-aggregated column from another
 * table. Development SQLite and a permissive local MySQL both tolerated it, so
 * the defect only appeared in production.
 *
 * Each test temporarily switches the session to the strict mode, exercises the
 * real code path, and restores the previous mode afterwards.
 */
class StrictGroupByTest extends TestCase
{
    use RefreshDatabase;

    private ?string $previousMode = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();

        // The whole point: make the dev database behave like production.
        try {
            $this->previousMode = DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;
            DB::statement("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        } catch (\Throwable $e) {
            // Not MySQL (e.g. the SQLite test connection): nothing to enforce.
            $this->previousMode = null;
        }

        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'status' => AcademicYear::ACTIVE,
            'is_active' => true,
            'is_default' => true,
        ]);

        $dept = Department::create(['name' => 'IPA', 'code' => 'IPA']);

        foreach ([['X IPA 1', 'X'], ['X IPA 2', 'X'], ['XI IPA 1', 'XI']] as [$name, $level]) {
            SchoolClass::create([
                'academic_year_id' => $year->id,
                'department_id' => $dept->id,
                'name' => $name,
                'level' => $level,
                'capacity' => 36,
                'status' => SchoolClass::ACTIVE,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if ($this->previousMode !== null) {
            try {
                DB::statement("SET SESSION sql_mode = " . DB::getPdo()->quote($this->previousMode));
            } catch (\Throwable) {
                // Nothing to restore.
            }
        }

        parent::tearDown();
    }

    public function test_the_session_actually_enforces_only_full_group_by(): void
    {
        if ($this->previousMode === null) {
            $this->markTestSkipped('Not running against MySQL.');
        }

        $mode = (string) DB::selectOne('SELECT @@SESSION.sql_mode AS m')->m;

        $this->assertStringContainsString('ONLY_FULL_GROUP_BY', $mode);
    }

    public function test_a_grouped_query_over_another_table_column_is_rejected(): void
    {
        if ($this->previousMode === null) {
            $this->markTestSkipped('Not running against MySQL.');
        }

        // The exact shape that broke production.
        $this->expectException(\Illuminate\Database\QueryException::class);

        SchoolClass::query()
            ->selectRaw('level, COUNT(*) as c')
            ->withCount(['liveEnrollments'])
            ->groupBy('level')
            ->get();
    }

    public function test_the_kesiswaan_dashboard_renders_under_strict_group_by(): void
    {
        $staff = $this->makeUser('kesiswaan');

        $this->actingAs($staff)
            ->get('/ruang-kerja/kesiswaan')
            ->assertOk()
            ->assertSee('Kesiswaan');
    }

    public function test_by_level_aggregation_is_correct(): void
    {
        $response = $this->actingAs($this->makeUser('kesiswaan'))
            ->get('/ruang-kerja/kesiswaan');

        $response->assertOk();

        $rows = $response->viewData('byLevel');

        // Two X classes and one XI class.
        $this->assertCount(2, $rows);

        $byLevel = $rows->keyBy('level');
        $this->assertSame(2, (int) $byLevel['X']->c);
        $this->assertSame(1, (int) $byLevel['XI']->c);
    }
}
