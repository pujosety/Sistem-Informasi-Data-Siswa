<?php

namespace Tests\Feature\Lms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LmsSchemaTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function the_lms_foundation_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('lms_courses'));
        $this->assertTrue(Schema::hasColumns('lms_courses', [
            'academic_year_id', 'semester_id', 'subject_id', 'classroom_id',
            'teacher_id', 'title', 'code', 'status',
        ]));

        $this->assertTrue(Schema::hasTable('lms_course_enrollments'));
        $this->assertTrue(Schema::hasColumns('lms_course_enrollments', [
            'course_id', 'student_id', 'enrolled_at', 'status',
        ]));

        $this->assertTrue(Schema::hasTable('lms_lessons'));
        $this->assertTrue(Schema::hasColumns('lms_lessons', [
            'course_id', 'title', 'body', 'position', 'status',
        ]));
    }

    /** @test */
    public function the_lms_tables_have_unique_course_code_and_enrollment_pair_indexes(): void
    {
        $this->assertTrue(Schema::hasIndex('lms_courses', ['code'], 'unique'));
        $this->assertTrue(Schema::hasIndex(
            'lms_course_enrollments',
            ['course_id', 'student_id'],
            'unique'
        ));
    }
}
