<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A semester calendar: the thing `grades.term` has been standing in for.
 *
 * WHY THIS EXISTS
 *
 * Grades are already split per term — the row is keyed on
 * (enrollment_id, subject_id, term) and `term` defaults to '1'. So the missing
 * piece is not a column, it is a calendar. Today `term` is a bare string with
 * no dates, no label, and no binding to an academic year, which means:
 *
 *   - nothing can answer "when does semester 1 run?"
 *   - '1' means the same thing in every year and cannot be joined
 *   - nothing can tell whether the current term is 1 or 2
 *   - a typo is accepted silently, producing a grade that belongs to no term
 *
 * A course in the LMS needs exactly these answers, and every one of them is
 * unavailable from a string.
 *
 * WHY grades.term IS NOT REPLACED
 *
 * Dropping it and forcing a foreign key would rewrite the unique constraint
 * that the existing gradebook and EnrollmentIntegrityTest depend on, and would
 * change the meaning of every stored score in one step. The term string stays
 * as the value; the new table becomes its calendar. semester_id is nullable for
 * the same reason, and is backfilled below for every existing row.
 *
 * WHY THE BACKFILL IS SAFE
 *
 * Each grade's term string names the semester it already belongs to, so the
 * mapping is a copy rather than a guess. A grade that already says '1' is
 * attached to semester 1 of ITS OWN academic year — the year is taken from the
 * enrolment, not from a global. Nothing is deleted and no score changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('semesters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            // '1' | '2' — a school may run odd terms, so this is a string
            // rather than an enum. The uniqueness below is what actually
            // prevents a duplicate, and an enum would make adding a third term
            // a migration.
            $table->string('name', 20);

            // Human label for the UI: "Semester 1", "Ganjil", "Mid Year".
            $table->string('label', 50)->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // At most one current semester per year. MySQL has no partial
            // index, so this is validated in the service layer — the same
            // approach academic_years.is_default already uses, and the reason
            // is recorded there.
            $table->boolean('is_current')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['academic_year_id', 'name'], 'semesters_year_name_unique');
            $table->index(['academic_year_id', 'is_current'], 'semesters_current_index');
        });

        Schema::create('grade_categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            // assignment | quiz | project | midterm | final. A string so a
            // school can add its own; weight is what makes a category count
            // toward the final score.
            $table->string('key', 30);
            $table->string('label', 100);
            $table->decimal('weight', 5, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['academic_year_id', 'key'], 'grade_categories_year_key_unique');
        });

        Schema::table('grades', function (Blueprint $table) {
            // Nullable on purpose. A grade that predates the calendar is still a
            // real score; the backfill below fills the ones it can attribute,
            // and leaving the column nullable means a grade whose enrolment has
            // no academic year is not silently discarded.
            $table->foreignId('semester_id')->nullable()->after('term')
                ->constrained('semesters')->nullOnDelete();

            // The table is grade_categories, not categories. An unconstrained
            // name here makes Laravel infer `categories`, which does not exist,
            // and MySQL reports error 1824 naming the table it looked for rather
            // than the one that does — so the message reads as a puzzle.
            // Naming the constraint removes the inference entirely.
            $table->foreignId('category_id')->nullable()->after('semester_id')
                ->constrained('grade_categories')->nullOnDelete();
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            // dropConstrainedForeignId infers the table from the column name, so
            // it would look for `categories` again and the rollback would fail
            // the same way the migration did. Dropping the constraints by name
            // is the only correct form for grade_categories.
            $table->dropForeign(['semester_id']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['semester_id', 'category_id']);
        });

        Schema::dropIfExists('grade_categories');
        Schema::dropIfExists('semesters');
    }

    /**
     * Delegate to the service rather than inlining the SQL.
     *
     * The transform is one-time work over rows that already exist, which is the
     * one thing a migration cannot be tested for: it runs against whatever the
     * database holds at deploy time. Keeping it in a service lets the arithmetic
     * be exercised on purpose-built data — see SemesterBackfillTest — while the
     * migration stays a schema change. Production behaviour is identical.
     */
    private function backfill(): void
    {
        $backfill = new \App\Services\SemesterBackfill();

        $backfill->createCalendar();
        $backfill->attachGrades();
    }
};
