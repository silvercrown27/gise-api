<?php

namespace Tests\Feature;

use App\Models\InstructorProfile;
use App\Models\ScholarUser;
use App\Models\User;
use App\Notifications\OtpVerificationNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_signup_without_role_defaults_to_student(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+254700000000',
            'password' => 'Passw0rd!23',
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertDatabaseHas('scholar_users', ['id' => $user->id, 'role' => 'student']);
        $this->assertDatabaseMissing('instructor_profiles', ['user_id' => $user->id]);
    }

    public function test_signup_as_student_sets_student_role(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+254700000000',
            'password' => 'Passw0rd!23',
            'role' => 'student',
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertDatabaseHas('scholar_users', ['id' => $user->id, 'role' => 'student']);
    }

    public function test_signup_as_instructor_sets_instructor_role_and_creates_profile(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'phone' => '+254700000001',
            'password' => 'Passw0rd!23',
            'role' => 'instructor',
        ]);

        $response->assertStatus(200);
        $user = User::where('email', 'john@example.com')->firstOrFail();
        $this->assertDatabaseHas('scholar_users', ['id' => $user->id, 'role' => 'instructor']);
        $this->assertDatabaseHas('instructor_profiles', ['user_id' => $user->id]);
    }

    public function test_signup_rejects_invalid_role(): void
    {
        $response = $this->postJson('/api/auth/signup', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
            'phone' => '+254700000000',
            'password' => 'Passw0rd!23',
            'role' => 'admin',
        ]);

        $response->assertStatus(422);
    }
}
