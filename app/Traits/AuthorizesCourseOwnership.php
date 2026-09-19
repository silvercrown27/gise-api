<?php

namespace App\Traits;

use App\Models\Course;
use App\Models\ScholarUser;
use Illuminate\Http\Request;

trait AuthorizesCourseOwnership
{
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

        return $user->role === 'instructor' && (string) $course->instructor_id === (string) $request->user()->id;
    }
}
