<?php

namespace App\Traits;

use App\Models\Course;
use App\Models\ScholarUser;
use Illuminate\Http\Request;

trait AuthorizesCourseOwnership
{
    /**
     * Whether the caller may author content (modules, lessons, quizzes, exams)
     * on this course: an admin, or an instructor approved to mentor one of its
     * cohorts. Course records and cohorts themselves are admin-only - see
     * isAdminRequest().
     */
    protected function canManageCourse(Request $request, ?Course $course): bool
    {
        if (!$course) {
            return false;
        }

        $user = ScholarUser::find($request->user()->id);

        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'instructor' && $course->isManageableBy($request->user()->id);
    }

    protected function isAdminRequest(Request $request): bool
    {
        $user = $request->user() ? ScholarUser::find($request->user()->id) : null;

        return $user && $user->role === 'admin';
    }
}
