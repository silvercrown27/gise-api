<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\Testimonial;
use App\Models\ScholarUser;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Testimonial::query();

            if ($q = trim($request->input('q', ''))) {
                $query->where('name', 'like', '%' . $q . '%');
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(10);

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TestimonialController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving testimonials.',
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

        $validator = Validations::validateTestimonial($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $data = $request->all();
            $testimonial = Testimonial::create($data);

            return response()->json([
                'status'  => 201,
                'message' => 'Testimonial created successfully.',
                'data'    => $testimonial,
            ], 201);
        } catch (\Exception $e) {
            Log::error('TestimonialController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the testimonial.',
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $testimonial = Testimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Testimonial not found.',
                ], 404);
            }

            return response()->json([
                'status' => 200,
                'data'   => $testimonial,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TestimonialController@show: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving the testimonial.',
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

        $validator = Validations::validateTestimonial($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        try {
            $testimonial = Testimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Testimonial not found.',
                ], 404);
            }

            $testimonial->update($request->all());

            return response()->json([
                'status'  => 200,
                'message' => 'Testimonial updated successfully.',
                'data'    => $testimonial,
            ], 200);
        } catch (\Exception $e) {
            Log::error('TestimonialController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the testimonial.',
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
            $testimonial = Testimonial::find($id);

            if (!$testimonial) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Testimonial not found.',
                ], 404);
            }

            $testimonial->delete();

            return response()->json([
                'status'  => 200,
                'message' => 'Testimonial deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('TestimonialController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the testimonial.',
            ], 500);
        }
    }
}
