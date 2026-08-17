<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebugCourseTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $instructor = User::factory()->create();
        ScholarUser::factory()->create(['id' => $instructor->id, 'role' => 'instructor']);
        Sanctum::actingAs($instructor);

        \Illuminate\Support\Facades\Route::post('/api/_debug_course', function (\Illuminate\Http\Request $request) {
            $user = \App\Models\ScholarUser::find($request->user()->id);
            $data = $request->all();
            $isAdmin = $user->role === 'admin';
            if (!$isAdmin || empty($data['instructor_id'])) {
                $data['instructor_id'] = $request->user()->id;
            }
            return response()->json([
                'user_id_type' => gettype($request->user()->id),
                'user_id' => $request->user()->id,
                'instructor_id_in_data' => $data['instructor_id'],
                'instructor_id_type' => gettype($data['instructor_id']),
                'is_valid_uuid' => \Illuminate\Support\Str::isUuid($data['instructor_id']),
            ]);
        })->middleware('auth:sanctum');

        $response = $this->postJson('/api/_debug_course', [
            'title' => 'New Course',
            'code' => 'ABC123',
            'slug' => 'new-course',
            'price' => 1000,
        ]);

        dump($response->json());
        $this->assertTrue(true);
    }
}
