<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $courses = Course::query()
            ->with(['academicYear', 'semester', 'subject', 'classroom'])
            ->when(! $user->isSuperAdmin() && ! $user->can('lms.course.view.all'),
                fn ($query) => $query->where('teacher_id', $user->id))
            ->latest('id')
            ->paginate(15);

        return view('lms.teacher.courses.index', compact('courses'));
    }

    public function create(): View
    {
        return view('lms.teacher.courses.create', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'semesters' => Semester::query()->with('academicYear')->orderByDesc('start_date')->get(),
            'subjects' => Subject::query()->orderBy('name')->get(),
            'classrooms' => SchoolClass::query()->active()->with('academicYear')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Course::class);

        $data = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'classroom_id' => ['required', 'integer', 'exists:classes,id'],
            'code' => ['required', 'string', 'max:80', 'unique:lms_courses,code'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string'],
        ]);

        $course = Course::create($data + [
            'teacher_id' => $request->user()->id,
            'status' => Course::DRAFT,
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Course berhasil dibuat sebagai draft.');
    }

    public function show(Course $course): View
    {
        $this->authorize('view', $course);

        return view('lms.teacher.courses.show', [
            'course' => $course->load(['academicYear', 'semester', 'subject', 'classroom', 'lessons']),
        ]);
    }
}
