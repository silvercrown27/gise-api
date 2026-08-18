<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use App\Helpers\Utilities;
use App\Helpers\Validations;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\InstructorPayout;
use App\Models\LessonProgress;
use App\Models\ScholarUser;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Course::with(['category', 'pace.certificationLevel.certificationType'])
                ->withCount(['enrollments', 'ratings']);

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($category = trim($request->input('category', ''))) {
                $query->whereHas('category', function ($q) use ($category) {
                    $q->where('slug', $category);
                });
            }

            if ($certificationLevel = trim($request->input('certification_level', ''))) {
                $query->whereHas('pace.certificationLevel', function ($q) use ($certificationLevel) {
                    $q->where('slug', $certificationLevel);
                });
            }

            if ($certificationType = trim($request->input('certification_type', ''))) {
                $query->whereHas('pace.certificationLevel.certificationType', function ($q) use ($certificationType) {
                    $q->where('slug', $certificationType);
                });
            }

            $query->where('status', 'published');

            $results = $query->orderBy('title', 'asc')->paginate(9);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving courses.',
            ], 500);
        }
    }

    public function summary(Request $request)
    {
        try {
            $instructorId = $request->user()->id;

            $courseIds = Course::where('instructor_id', $instructorId)->pluck('id');

            $totalCourses = $courseIds->count();
            $publishedCourses = Course::where('instructor_id', $instructorId)
                ->where('status', 'published')
                ->count();

            $totalRegistrations = Enrollment::whereIn('course_id', $courseIds)->count();

            $totalEarnings = InstructorPayout::where('instructor_id', $instructorId)
                ->where('status', 'paid')
                ->sum('net_amount');

            $pendingEarnings = InstructorPayout::where('instructor_id', $instructorId)
                ->where('status', 'pending')
                ->sum('net_amount');

            return response()->json([
                'status' => 200,
                'data' => [
                    'total_courses' => $totalCourses,
                    'published_courses' => $publishedCourses,
                    'total_registrations' => $totalRegistrations,
                    'total_earnings' => (int) $totalEarnings,
                    'pending_earnings' => (int) $pendingEarnings,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@summary: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the summary.',
            ], 500);
        }
    }

    public function curriculum(Request $request, string $id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $userId = $request->user()->id;
            $user = ScholarUser::find($userId);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor'
                && (string) $course->instructor_id === (string) $userId;

            $enrollment = Enrollment::where('course_id', $course->id)
                ->where('learner_id', $userId)
                ->first();

            if (!$isAdmin && !$isOwningInstructor && !$enrollment) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'You must be enrolled in this course to view its curriculum.',
                ], 403);
            }

            $progressByLessonId = [];
            if ($enrollment) {
                $progressByLessonId = LessonProgress::where('enrollment_id', $enrollment->id)
                    ->get()
                    ->keyBy('lesson_id');
            }

            $modules = $course->modules()
                ->with(['lessons' => function ($query) {
                    $query->orderBy('order_index', 'asc')->with('resources');
                }])
                ->orderBy('order_index', 'asc')
                ->get();

            $modules->each(function ($module) use ($progressByLessonId) {
                $module->lessons->each(function ($lesson) use ($progressByLessonId) {
                    $progress = $progressByLessonId[$lesson->id] ?? null;
                    $lesson->progress_status = $progress->status ?? 'not_started';
                    $lesson->progress_id = $progress->id ?? null;
                });
            });

            return response()->json([
                'status' => 200,
                'data' => [
                    'course' => $course,
                    'enrollment' => $enrollment,
                    'modules' => $modules,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@curriculum: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the curriculum.',
            ], 500);
        }
    }

    public function mine(Request $request)
    {
        try {
            $query = Course::with(['category', 'pace.certificationLevel.certificationType'])
                ->withCount(['enrollments', 'ratings'])
                ->where('instructor_id', $request->user()->id);

            if ($q = trim($request->input('q', ''))) {
                $query->where('title', 'like', '%' . $q . '%');
            }

            if ($status = trim($request->input('status', ''))) {
                $query->where('status', $status);
            }

            $results = $query->orderBy('created_at', 'desc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@mine: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving your courses.',
            ], 500);
        }
    }

    public function popular(Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 6);
            $limit = $limit > 0 && $limit <= 24 ? $limit : 6;

            $results = Course::with(['category', 'pace.certificationLevel.certificationType'])
                ->withCount(['enrollments', 'ratings'])
                ->where('status', 'published')
                ->orderByDesc('enrollments_count')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@popular: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving popular courses.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $data = $request->all();
        $isAdmin = $user->role === 'admin';

        if (!$isAdmin || empty($data['instructor_id'])) {
            $data['instructor_id'] = $request->user()->id;
        }

        if ($request->hasFile('thumbnail')) {
            $upload = $this->uploadThumbnail($request);
            if (!$upload['success']) {
                return response()->json([
                    'status'  => 500,
                    'message' => $upload['message'],
                ], 500);
            }
            $data['thumbnail_url'] = $upload['url'];
        }

        $validator = Validations::validateCourse($data);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $course = Course::create($data);
            $course->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Course created successfully.',
                'data'    => $course,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CourseController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the course.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $course = Course::with(['category', 'instructor', 'pace.certificationLevel.certificationType'])
                ->withCount(['enrollments', 'ratings'])
                ->find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $course,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the course.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor' && (string) $course->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $data = $request->all();

            if ($request->hasFile('thumbnail')) {
                $upload = $this->uploadThumbnail($request);
                if (!$upload['success']) {
                    return response()->json([
                        'status'  => 500,
                        'message' => $upload['message'],
                    ], 500);
                }
                $data['thumbnail_url'] = $upload['url'];
            }

            $validator = Validations::validateCourse($data, $id);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Validation failed.',
                    'errors'  => $validator->messages(),
                ], 422);
            }

            if (!$isAdmin) {
                unset($data['instructor_id']);
            }

            $course->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Course updated successfully.',
                'data'    => $course,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the course.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        try {
            $course = Course::find($id);

            if (!$course) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Course not found.',
                ], 404);
            }

            $user = ScholarUser::find($request->user()->id);

            $isAdmin = $user && $user->role === 'admin';
            $isOwningInstructor = $user && $user->role === 'instructor' && (string) $course->instructor_id === (string) $request->user()->id;

            if (!$isAdmin && !$isOwningInstructor) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $course->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Course deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CourseController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the course.',
            ], 500);
        }
    }

    private function uploadThumbnail(Request $request): array
    {
        $upload = Utilities::uploadFile($request->file('thumbnail'), 'course-thumbnails');

        if ($upload['status'] !== 200) {
            return ['success' => false, 'message' => $upload['message']];
        }

        return ['success' => true, 'url' => Storage::url($upload['path'])];
    }
}
