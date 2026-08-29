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

            $results = $query->orderBy('name', 'asc')->paginate(10);

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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateCategory($request->all(), $id);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Category not found.',
                ], 404);
            }

            $category->update($request->all());

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
        $user = ScholarUser::find($request->user()->id);

        if (!$user || !in_array($user->role, ['instructor', 'admin'])) {
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
