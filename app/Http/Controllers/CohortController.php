<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Cohort;
use App\Services\LifecycleNotifier;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\ScholarUser;
use App\Services\ModuleProgressService;
use App\Traits\AuthorizesCourseOwnership;

class CohortController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = Cohort::with('course:id,title,slug,code')->withCount('enrollments');

            if ($q = trim($request->input('q', ''))) {
                $query->where('label', 'like', '%' . $q . '%');
            }

            if ($courseId = trim($request->input('course_id', ''))) {
                $query->where('course_id', $courseId);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 100);
            $results = $query->orderBy('start_date', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving cohorts.',
            ], 500);
        }
    }

    public function next(Request $request)
    {
        try {
            $cohort = Cohort::with('course')
                ->whereIn('status', ['upcoming', 'open'])
                ->whereDate('start_date', '>=', now()->toDateString())
                ->whereHas('course', function ($q) {
                    $q->where('status', 'published');
                })
                ->whereColumn('seats_taken', '<', 'capacity')
                ->orderBy('start_date', 'asc')
                ->first();

            if (!$cohort) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'No upcoming cohort found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $cohort,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@next: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the next cohort.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // Cohort dates, windows and capacity are set by the admin who manages
        // every course - mentors only teach them.
        if (!$this->isAdminRequest($request)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCohort($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $cohort = Cohort::create($data);
            $cohort->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Cohort created successfully.',
                'data'    => $cohort,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CohortController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the cohort.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $cohort = Cohort::withCount('enrollments')->find($id);

            if (!$cohort) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Cohort not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $cohort,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the cohort.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        // Cohort dates, windows and capacity are set by the admin who manages
        // every course - mentors only teach them.
        if (!$this->isAdminRequest($request)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCohort($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $cohort = Cohort::find($id);

            if (!$cohort) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Cohort not found.',
                ], 404);
            }

            $before = $this->scheduleSnapshot($cohort);
            $cohort->update($request->all());
            $scheduleChanged = $before !== $this->scheduleSnapshot($cohort->fresh());
            $cohort->syncSeatsTaken();

            if ($scheduleChanged) {
                LifecycleNotifier::cohortChanged($cohort);
            }

            return response()->json([
                'status'  => 200,
                'message' => 'Cohort updated successfully.',
                'data'    => $cohort,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the cohort.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        // Cohort dates, windows and capacity are set by the admin who manages
        // every course - mentors only teach them.
        if (!$this->isAdminRequest($request)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $cohort = Cohort::find($id);

            if (!$cohort) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Cohort not found.',
                ], 404);
            }

            $cohort->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Cohort deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the cohort.',
            ], 500);
        }
    }

    public function moduleProgress(Request $request, string $id)
    {
        try {
            $cohort = Cohort::with('course')->find($id);

            if (!$cohort) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Cohort not found.',
                ], 404);
            }

            if (!$this->canViewCohortProgress($request, $cohort)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            return response()->json([
                'status' => 200,
                'data'   => [
                    'cohort' => $cohort,
                    'modules' => ModuleProgressService::forCohort($cohort),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('CohortController@moduleProgress: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving module progress.',
            ], 500);
        }
    }

    private function canViewCohortProgress(Request $request, Cohort $cohort): bool
    {
        if ($this->canManageCourse($request, $cohort->course)) {
            return true;
        }

        return CohortMentorApplication::where('cohort_id', $cohort->id)
            ->where('instructor_id', $request->user()->id)
            ->where('status', 'approved')
            ->exists();
    }

    /** What learners and mentors would notice changing: when and where the cohort runs. Dates compare as calendar days. */
    private function scheduleSnapshot(Cohort $cohort): array
    {
        return [
            $cohort->start_date?->toDateString(),
            $cohort->end_date?->toDateString(),
            $cohort->mode,
            $cohort->location_city,
            $cohort->location_country,
        ];
    }
}
