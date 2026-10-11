<?php

namespace Tests\Feature\Lms;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LmsAssignment;
use App\Models\LmsAssignmentSubmission;
use App\Models\Student;
use App\Models\User;
use App\Models\WorkspaceFile;
use App\Models\WorkspaceVersionScan;
use App\Services\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Lms\LmsTestCase;

class WorkspaceAssignmentSnapshotTest extends LmsTestCase
{
    public function test_submission_attaches_owned_drive_snapshot_and_download_is_authorized_and_immutable(): void
    {
        app(RoleSeeder::class)->run();
        Storage::fake('workspace');
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $teacher->assignRole('wali_kelas');
        $studentUser = User::factory()->create(['email_verified_at' => now()]);
        $studentUser->assignRole('siswa');
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $course = $this->courseFor($teacher, $student);
        CourseEnrollment::create(['school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $student->id, 'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now()]);
        $assignment = LmsAssignment::create(['school_id' => $course->school_id, 'course_id' => $course->id, 'title' => 'A', 'description' => '', 'max_score' => 100, 'submission_type' => 'text_file', 'max_attempts' => 2, 'allow_late' => true, 'status' => LmsAssignment::PUBLISHED]);
        $path = 'snapshot/first.txt';
        Storage::disk('workspace')->put($path, 'first bytes');
        $file = WorkspaceFile::create(['public_id' => (string) \Illuminate\Support\Str::uuid(), 'school_id' => $course->school_id, 'owner_id' => $studentUser->id, 'name' => 'work.txt', 'mime_type' => 'text/plain', 'size_bytes' => 11, 'current_version' => 1]);
        $version = $file->versions()->create(['version_number' => 1, 'disk' => 'workspace', 'path' => $path, 'mime_type' => 'text/plain', 'size_bytes' => 11, 'created_by' => $studentUser->id]);
        WorkspaceVersionScan::create([
            'workspace_file_version_id' => $version->id,
            'status' => 'clean',
            'checksum_sha256' => hash('sha256', 'first bytes'),
            'scanned_bytes' => 11,
            'attempts' => 1,
            'scanned_at' => now(),
        ]);

        $foreignUser = User::factory()->create(['email_verified_at' => now()]);
        $foreignFile = WorkspaceFile::create(['public_id' => (string) \Illuminate\Support\Str::uuid(), 'school_id' => $course->school_id, 'owner_id' => $foreignUser->id, 'name' => 'foreign.txt', 'mime_type' => 'text/plain', 'size_bytes' => 1, 'current_version' => 1]);
        $foreignFile->versions()->create(['version_number' => 1, 'disk' => 'workspace', 'path' => $path, 'mime_type' => 'text/plain', 'size_bytes' => 11, 'created_by' => $foreignUser->id]);
        $foreignSchoolId = DB::table('schools')->insertGetId(['slug' => 'snapshot-other-'.$studentUser->id, 'name' => 'Other School', 'is_active' => true, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()]);
        $crossSchoolPublicId = (string) \Illuminate\Support\Str::uuid();
        $crossSchoolFileId = DB::table('workspace_files')->insertGetId([
            'public_id' => $crossSchoolPublicId, 'school_id' => $foreignSchoolId, 'owner_id' => $studentUser->id,
            'name' => 'other-school.txt', 'mime_type' => 'text/plain', 'size_bytes' => 11,
            'source_application' => 'drive', 'current_version' => 1, 'is_favorite' => false,
            'permission_metadata' => json_encode(['visibility' => 'private']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_file_versions')->insert([
            'workspace_file_id' => $crossSchoolFileId, 'version_number' => 1, 'disk' => 'workspace',
            'path' => $path, 'mime_type' => 'text/plain', 'size_bytes' => 11,
            'created_by' => $studentUser->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->actingAs($studentUser)->post(route('lms.student.assignments.submit', [$course, $assignment]), ['body' => 'bad', 'workspace_file' => $foreignFile->public_id])->assertNotFound();
        $this->actingAs($studentUser)->post(route('lms.student.assignments.submit', [$course, $assignment]), ['body' => 'bad', 'workspace_file' => $crossSchoolPublicId])->assertNotFound();
        $this->actingAs($studentUser)->post(route('lms.student.assignments.submit', [$course, $assignment]), ['body' => 'answer', 'workspace_file' => $file->public_id, 'file' => UploadedFile::fake()->createWithContent('legacy.pdf', '%PDF-1.4 legacy')])->assertRedirect();
        $submission = LmsAssignmentSubmission::firstOrFail();
        $this->assertSame($version->id, $submission->workspace_file_version_id);
        $this->assertNotNull($submission->file_path);
        $this->assertTrue(Storage::disk('local')->exists($submission->file_path));
        $this->actingAs($studentUser)->get(route('lms.student.submissions.workspace-download', $submission))->assertOk()->assertStreamedContent('first bytes');
        Storage::disk('workspace')->put('snapshot/second.txt', 'second bytes');
        $file->versions()->create(['version_number' => 2, 'disk' => 'workspace', 'path' => 'snapshot/second.txt', 'mime_type' => 'text/plain', 'size_bytes' => 12, 'created_by' => $studentUser->id]);
        $file->update(['current_version' => 2]);
        $this->actingAs($teacher)->get(route('lms.teacher.submissions.workspace-download', $submission))->assertOk()->assertStreamedContent('first bytes');
        $other = User::factory()->create(['email_verified_at' => now()]);
        $other->assignRole('siswa');
        $otherStudent = Student::factory()->create(['user_id' => $other->id]);
        CourseEnrollment::create(['school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $otherStudent->id, 'status' => CourseEnrollment::ACTIVE, 'enrolled_at' => now()]);
        $this->actingAs($other)->get(route('lms.student.submissions.workspace-download', $submission))->assertForbidden();
        $unrelatedTeacher = User::factory()->create(['email_verified_at' => now()]);
        $unrelatedTeacher->assignRole('wali_kelas');
        $this->actingAs($unrelatedTeacher)->get(route('lms.teacher.submissions.workspace-download', $submission))->assertForbidden();

        $this->actingAs($studentUser)->post(route('workspace.drive.files.trash', $file))->assertRedirect();
        $this->actingAs($studentUser)->get(route('lms.student.submissions.workspace-download', $submission))->assertOk()->assertStreamedContent('first bytes');
        $this->actingAs($teacher)->get(route('lms.teacher.submissions.workspace-download', $submission))->assertOk()->assertStreamedContent('first bytes');
        $this->actingAs($studentUser)->get(route('workspace.drive.files.download', $file))->assertNotFound();
    }

    private function courseFor(User $teacher, Student $student): Course
    {
        $schoolId = DB::table('schools')->where('is_default', true)->value('id') ?? DB::table('schools')->value('id');
        $year = DB::table('academic_years')->insertGetId(['name'=>'SNAP-'.$student->id,'start_date'=>'2026-07-01','end_date'=>'2027-06-30','is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        $semester = DB::table('semesters')->insertGetId(['academic_year_id'=>$year,'name'=>'1','label'=>'Ganjil','is_current'=>true,'start_date'=>'2026-07-01','end_date'=>'2026-12-31','created_at'=>now(),'updated_at'=>now()]);
        $subject = DB::table('subjects')->insertGetId(['name'=>'SNAP '.$student->id,'code'=>'SN'.$student->id,'grade_level'=>'VII','created_at'=>now(),'updated_at'=>now()]);
        $department = DB::table('departments')->insertGetId(['name'=>'SNAP '.$student->id,'code'=>'SD'.$student->id,'created_at'=>now(),'updated_at'=>now()]);
        $class = DB::table('classes')->insertGetId(['academic_year_id'=>$year,'department_id'=>$department,'name'=>'SNAP '.$student->id,'level'=>'VII','created_at'=>now(),'updated_at'=>now()]);
        return Course::create(['school_id'=>$schoolId,'academic_year_id'=>$year,'semester_id'=>$semester,'subject_id'=>$subject,'classroom_id'=>$class,'teacher_id'=>$teacher->id,'code'=>'SN'.$student->id,'title'=>'Snapshot','status'=>Course::PUBLISHED]);
    }
}
