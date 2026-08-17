<?php

namespace Tests\Feature;

use App\Models\ScholarUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebugCourseTest2 extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $instructor = User::factory()->create();
        Sanctum::actingAs($instructor);

        \Illuminate\Support\Facades\Route::post('/api/_debug_course2', function (\Illuminate\Http\Request $request) {
            return response()->json([
                'class' => get_class($request->user()->id),
            ]);
        })->middleware('auth:sanctum');

        $response = $this->postJson('/api/_debug_course2', []);
        dump($response->json());
        $this->assertTrue(true);
    }
}
