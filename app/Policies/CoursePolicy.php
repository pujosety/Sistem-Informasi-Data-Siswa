<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\Services\ClassScope;

class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('lms.course.view');
    }

    public function create(User $user): bool
    {
        return $user->can('lms.course.create');
    }

    public function view(User $user, Course $course): bool
    {
        if (! $user->can('lms.course.view')) {
            return false;
        }

        return $user->isSuperAdmin()
            || $course->teacher_id === $user->id
            || app(ClassScope::class)->canView($user, $course->classroom_id);
    }

    public function update(User $user, Course $course): bool
    {
        if (! $user->can('lms.course.update')) {
            return false;
        }

        return $user->isSuperAdmin()
            || $course->teacher_id === $user->id
            || app(ClassScope::class)->canView($user, $course->classroom_id);
    }
}
