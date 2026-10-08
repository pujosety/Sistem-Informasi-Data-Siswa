<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function index(Course $course): View
    {
        $this->authorizeCourse($course);

        $enrollments = $course->enrollments()
            ->where('status', CourseEnrollment::ACTIVE)
            ->with(['student.user'])
            ->latest('enrolled_at')
            ->paginate(30);

        $availableStudents = Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $course->academic_year_id)
                ->where('classroom_id', $course->classroom_id)
                ->whereIn('status', Enrollment::LIVE_STATUSES))
            ->whereDoesntHave('courseEnrollments', fn ($query) => $query
                ->where('course_id', $course->id)
                ->where('status', CourseEnrollment::ACTIVE))
            ->with('user')
            ->orderBy('full_name')
            ->get();

        return view('lms.teacher.enrollments.index', compact('course', 'enrollments', 'availableStudents'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $eligible = Student::query()
            ->whereKey($data['student_id'])
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $course->academic_year_id)
                ->where('classroom_id', $course->classroom_id)
                ->whereIn('status', Enrollment::LIVE_STATUSES))
            ->exists();

        abort_unless($eligible, 422, 'Siswa bukan anggota kelas course ini.');

        CourseEnrollment::updateOrCreate(
            ['course_id' => $course->id, 'student_id' => $data['student_id']],
            [
                'school_id' => $course->school_id,
                'enrolled_at' => now(),
                'status' => CourseEnrollment::ACTIVE,
            ]
        );

        return redirect()->route('lms.teacher.enrollments.index', $course)
            ->with('success', 'Siswa berhasil ditambahkan ke course.');
    }

    public function destroy(Course $course, CourseEnrollment $enrollment): RedirectResponse
    {
        $this->authorizeCourse($course);
        abort_unless($enrollment->course_id === $course->id, 404);

        $enrollment->update(['status' => CourseEnrollment::INACTIVE]);

        return redirect()->route('lms.teacher.enrollments.index', $course)
            ->with('success', 'Siswa dikeluarkan dari course.');
    }

    private function authorizeCourse(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.enrollment.manage'), 403);
        $this->authorize('update', $course);
    }
}
