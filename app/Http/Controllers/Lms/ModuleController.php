<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LmsModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function create(Course $course): View
    {
        $this->authorizeCourse($course);

        return view('lms.teacher.modules.create', [
            'course' => $course->load(['subject', 'classroom']),
            'nextPosition' => ((int) $course->modules()->max('position')) + 1,
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:20000'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $course->modules()->create([
            'school_id' => $course->school_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'position' => $data['position'] ?? (((int) $course->modules()->max('position')) + 1),
            'status' => LmsModule::DRAFT,
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Bab pembelajaran berhasil dibuat sebagai draft.');
    }

    private function authorizeCourse(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.lesson.manage'), 403);
        $this->authorize('update', $course);
    }
}
