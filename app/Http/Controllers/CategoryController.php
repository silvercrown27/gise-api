<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Category;
use App\Models\ScholarUser;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Category::withCount(['courses as available_courses_count' => function ($q) {
                $q->where('status', 'published')->where('admin_approval_status', 'approved');
            }]);

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            if ($classification = trim($request->input('classification', ''))) {
                $query->where('classification', $classification);
            }

            $perPage = min(max((int) $request->input('per_page', 10), 1), 200);
            $results = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CategoryController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving categories.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        // Sub-distinctions shape the public catalogue, so only admins manage them.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCategory($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $category = Category::create($data);
            $category->refresh();

            return response()->json([
                'status'  => 201,
                'message' => 'Category created successfully.',
                'data'    => $category,
            ], 201);
        } catch (\Exception $e) {
            Log::error('CategoryController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the category.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $category = Category::withCount('courses')->find($id);

            if (!$category) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Category not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $category,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CategoryController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the category.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        // Sub-distinctions shape the public catalogue, so only admins manage them.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'status'  => 404,
                'message' => 'Category not found.',
            ], 404);
        }

        // Moving a sub-distinction to another level is allowed, but an edit
        // that doesn't mention the level keeps the current one.
        $data = array_merge(['classification' => $category->classification], $request->all());

        $validator = Validations::validateCategory($data, $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        if ($data['classification'] !== $category->classification && $category->courses()->exists()) {
            return response()->json([
                'status'  => 422,
                'message' => 'This sub-distinction has courses - move them before changing its level.',
                'errors'  => ['classification' => ['This sub-distinction has courses - move them before changing its level.']],
            ], 422);
        }

        try {
            $category->update($data);

            return response()->json([
                'status'  => 200,
                'message' => 'Category updated successfully.',
                'data'    => $category,
            ], 200);
        } catch (\Exception $e) {
            Log::error('CategoryController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the category.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = $request->scholarUser();

        // Sub-distinctions shape the public catalogue, so only admins manage them.
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Category not found.',
                ], 404);
            }

            if ($category->courses()->exists()) {
                return response()->json([
                    'status'  => 422,
                    'message' => 'Move this sub-distinction\'s courses elsewhere before deleting it.',
                ], 422);
            }

            $category->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Category deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('CategoryController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the category.',
            ], 500);
        }
    }
}
