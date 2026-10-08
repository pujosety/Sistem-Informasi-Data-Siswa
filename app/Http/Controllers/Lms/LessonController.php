<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function create(Course $course): View
    {
        $this->authorizeCourse($course);

        return view('lms.teacher.lessons.create', [
            'course' => $course->load(['subject', 'classroom', 'modules' => fn ($query) => $query->orderBy('position')]),
            'nextPosition' => ((int) $course->lessons()->max('position')) + 1,
        ]);
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeCourse($course);

        $data = $request->validate([
            'module_id' => [
                'nullable',
                'integer',
                Rule::exists('lms_modules', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->where('school_id', $course->school_id)),
            ],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:1'],
        ]);

        $course->lessons()->create([
            'module_id' => $data['module_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'position' => $data['position'] ?? (((int) $course->lessons()->max('position')) + 1),
            'status' => Lesson::DRAFT,
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Materi berhasil disimpan sebagai draft.');
    }

    public function edit(Course $course, Lesson $lesson): View
    {
        $this->authorizeLesson($course, $lesson);

        return view('lms.teacher.lessons.edit', [
            'course' => $course->load(['subject', 'classroom', 'modules' => fn ($query) => $query->orderBy('position')]),
            'lesson' => $lesson,
        ]);
    }

    public function update(Request $request, Course $course, Lesson $lesson): RedirectResponse
    {
        $this->authorizeLesson($course, $lesson);

        $data = $request->validate([
            'module_id' => [
                'nullable',
                'integer',
                Rule::exists('lms_modules', 'id')->where(fn ($query) => $query
                    ->where('course_id', $course->id)
                    ->where('school_id', $course->school_id)),
            ],
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string'],
            'position' => ['required', 'integer', 'min:1'],
        ]);

        $lesson->update([
            'module_id' => $data['module_id'] ?? null,
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'position' => $data['position'],
        ]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Materi berhasil diperbarui.');
    }

    public function publish(Course $course, Lesson $lesson): RedirectResponse
    {
        $this->authorizeLesson($course, $lesson);

        $lesson->update(['status' => Lesson::PUBLISHED]);

        return redirect()->route('lms.teacher.courses.show', $course)
            ->with('success', 'Materi berhasil diterbitkan.');
    }

    private function authorizeCourse(Course $course): void
    {
        abort_unless(request()->user()?->can('lms.lesson.manage'), 403);
        $this->authorize('update', $course);
    }

    private function authorizeLesson(Course $course, Lesson $lesson): void
    {
        abort_unless($lesson->course_id === $course->id, 404);
        $this->authorizeCourse($course);
    }
}
