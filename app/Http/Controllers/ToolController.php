<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Tool;
use App\Traits\AuthorizesCourseOwnership;

/**
 * Software/tools catalogue (e.g. Ansys) with licence prices. Anyone may list
 * them; only admins manage them.
 */
class ToolController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            $query = Tool::withCount('courses');

            if ($q = trim($request->input('q', ''))) {
                $query->whereRaw("name like ? escape '!'", ['%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%']);
            }

            // The tools list is public; the usage filter and counts are for admins.
            $counts = null;
            // (this route has no auth middleware, so read the token through the sanctum guard)
            $authUser = $request->user('sanctum');
            if ($authUser && \App\Models\ScholarUser::find($authUser->id)?->isAdmin()) {
                $total = Tool::count();
                $withCourses = Tool::has('courses')->count();
                $counts = ['total' => $total, 'with_courses' => $withCourses, 'without_courses' => $total - $withCourses];

                match ((string) $request->input('has_courses', '')) {
                    'with' => $query->has('courses'),
                    'without' => $query->doesntHave('courses'),
                    default => null,
                };
            }

            $perPage = min(max((int) $request->input('per_page', 20), 1), 200);

            return response()->json([
                'status' => 200,
                'data'   => $query->orderBy('name')->paginate($perPage),
                'counts' => $counts,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ToolController@index: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while retrieving tools.'], 500);
        }
    }

    public function store(Request $request)
    {
        if (!$this->isAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $validator = Validations::validateTool($request->all());
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => 'Validation failed.', 'errors' => $validator->messages()], 422);
        }

        try {
            $tool = Tool::create($request->all());
            $tool->refresh();

            return response()->json(['status' => 201, 'message' => 'Tool created successfully.', 'data' => $tool], 201);
        } catch (\Exception $e) {
            Log::error('ToolController@store: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while creating the tool.'], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        if (!$this->isAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $tool = Tool::find($id);
        if (!$tool) {
            return response()->json(['status' => 404, 'message' => 'Tool not found.'], 404);
        }

        $validator = Validations::validateTool($request->all(), $id);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => 'Validation failed.', 'errors' => $validator->messages()], 422);
        }

        try {
            $tool->update($request->all());

            return response()->json(['status' => 200, 'message' => 'Tool updated successfully.', 'data' => $tool], 200);
        } catch (\Exception $e) {
            Log::error('ToolController@update: ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'An error occurred while updating the tool.'], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        if (!$this->isAdminRequest($request)) {
            return response()->json(['status' => 403, 'message' => 'Forbidden.'], 403);
        }

        $tool = Tool::find($id);
        if (!$tool) {
            return response()->json(['status' => 404, 'message' => 'Tool not found.'], 404);
        }

        $tool->delete();

        return response()->json(['status' => 200, 'message' => 'Tool deleted successfully.'], 200);
    }
}
