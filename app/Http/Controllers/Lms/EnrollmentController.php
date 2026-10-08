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

        if (is_array($request->input('student_ids')) && $request->input('student_ids') !== []) {
            $data = $request->validate([
                'student_ids' => ['required', 'array', 'min:1'],
                'student_ids.*' => ['integer', 'distinct', 'exists:students,id'],
            ]);
            $studentIds = array_values(array_unique(array_map('intval', $data['student_ids'])));
        } else {
            $data = $request->validate([
                'student_id' => ['required', 'integer', 'exists:students,id'],
            ]);
            $studentIds = [(int) $data['student_id']];
        }

        $eligibleIds = Student::query()
            ->whereIn('id', $studentIds)
            ->whereHas('enrollments', fn ($query) => $query
                ->where('academic_year_id', $course->academic_year_id)
                ->where('classroom_id', $course->classroom_id)
                ->whereIn('status', Enrollment::LIVE_STATUSES))
            ->pluck('id')
            ->all();

        abort_unless(count($eligibleIds) === count($studentIds), 422, 'Semua siswa harus berasal dari kelas course ini.');

        foreach ($eligibleIds as $studentId) {
            CourseEnrollment::updateOrCreate(
                ['course_id' => $course->id, 'student_id' => $studentId],
                [
                    'school_id' => $course->school_id,
                    'enrolled_at' => now(),
                    'status' => CourseEnrollment::ACTIVE,
                ]
            );
        }

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
