<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DebugCourseTest3 extends TestCase
{
    use RefreshDatabase;

    public function test_debug(): void
    {
        $instructor = User::factory()->create();
        dump(['direct_find' => gettype(User::find($instructor->id)->id), 'direct_class' => is_object($instructor->id) ? get_class($instructor->id) : gettype($instructor->id)]);
        $this->assertTrue(true);
    }
}
