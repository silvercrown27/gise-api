<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\CohortMentorApplication;
use App\Models\Course;
use App\Models\CourseLead;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\InstructorProfile;
use App\Models\Notification as InApp;
use App\Models\Payment;
use App\Models\ScholarUser;
use App\Models\User;
use App\Notifications\CourseBrochureNotification;
use App\Notifications\CourseRegistrationNotification;
use App\Notifications\InstructorAccountDecisionNotification;
use App\Notifications\MentorApplicationApprovedNotification;
use App\Notifications\NewBrochureRequestAdminNotification;
use App\Notifications\NewPaymentAdminNotification;
use App\Notifications\OtpVerificationNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RoleChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\WelcomeNotification;
use Ichtrojan\Otp\Models\Otp as OtpRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

/**
 * Who is told what, on the platform and by email - and that a broken mail
 * server never breaks or falsifies anything.
 */
class NotificationsAndEmailsTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk_test_flow';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paystack.secret_key' => self::SECRET,
            'app.frontend_url' => 'https://giseafrica.test',
        ]);
    }

    private function person(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        ScholarUser::factory()->create(['id' => $user->id, 'role' => $role]);

        return $user;
    }

    private function inApp(User $user, ?string $type = null): int
    {
        return InApp::where('user_id', $user->id)->when($type, fn ($q) => $q->where('type', $type))->count();
    }

    /** A cohort with an approved mentor. Free unless a price is given. */
    private function cohort(int $price = 0, ?User $mentor = null): Cohort
    {
        $course = Course::factory()->create(['price' => $price, 'currency' => 'USD', 'max_students' => null]);
        $cohort = Cohort::factory()->create([
            'course_id' => $course->id, 'price' => $price, 'start_date' => now()->addWeek(),
            'status' => 'open', 'capacity' => 30, 'seats_taken' => 0,
        ]);

        if ($mentor) {
            CohortMentorApplication::factory()->approved()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id]);
        }

        return $cohort;
    }

    private function pendingPayment(User $learner, Cohort $cohort): Payment
    {
        return Payment::factory()->create([
            'learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id,
            'with_licences' => false, 'amount' => 650, 'currency' => 'USD', 'payment_gateway' => 'paystack',
            'reference' => 'GISE-FLOW', 'status' => 'pending', 'paid_at' => null, 'invoice_number' => null,
        ]);
    }

    private function payWebhook(): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['event' => 'charge.success', 'data' => [
            'id' => 777, 'status' => 'success', 'reference' => 'GISE-FLOW', 'amount' => 65000, 'currency' => 'USD', 'channel' => 'card',
        ]]);

        return $this->call('POST', '/api/paystack/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::SECRET),
        ], $body);
    }

    /** Makes every email fail to send, as if the mail server were down. */
    private function breakMail(): void
    {
        app('mail.manager')->extend('exploding', fn () => new ExplodingTransport());
        config(['mail.mailers.exploding' => ['transport' => 'exploding'], 'mail.default' => 'exploding']);
        app('mail.manager')->purge('exploding');
    }

    // ── signup ────────────────────────────────────────────────────────────────

    public function test_signup_sends_one_welcome_email_and_no_verification_code(): void
    {
        NotificationFacade::fake();

        $this->postJson('/api/auth/signup', [
            'first_name' => 'Amina', 'last_name' => 'Otieno', 'email' => 'amina@example.com', 'phone' => '+254700111222',
            'password' => 'Test@12345', 'confirmPassword' => 'Test@12345', 'role' => 'student',
        ])->assertStatus(200);

        $user = User::where('email', 'amina@example.com')->firstOrFail();
        NotificationFacade::assertSentTo($user, WelcomeNotification::class);
        NotificationFacade::assertSentToTimes($user, WelcomeNotification::class, 1);
        NotificationFacade::assertNotSentTo($user, OtpVerificationNotification::class);
    }

    // ── learners ──────────────────────────────────────────────────────────────

    public function test_free_registration_notifies_learner_and_the_cohorts_mentors_and_emails_the_learner(): void
    {
        NotificationFacade::fake();
        $mentor = $this->person('instructor');
        $stranger = $this->person('instructor');
        $superAdmin = $this->person('super_admin');
        $learner = $this->person('student');
        $cohort = $this->cohort(0, $mentor);
        Sanctum::actingAs($learner);

        $this->postJson('/api/enrollments', ['learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id])
            ->assertStatus(201);

        $this->assertSame(1, $this->inApp($learner, 'enrollment'));
        $this->assertSame(1, $this->inApp($mentor, 'enrollment'), 'the mentor of that cohort hears about the new learner');
        $this->assertSame(0, $this->inApp($stranger), 'other mentors are not told');
        $this->assertSame(0, $this->inApp($superAdmin), 'free registrations are not a payment');

        NotificationFacade::assertSentTo($learner, CourseRegistrationNotification::class);
        NotificationFacade::assertNotSentTo($learner, PaymentReceivedNotification::class);
        NotificationFacade::assertNotSentTo($mentor, CourseRegistrationNotification::class);
    }

    public function test_a_paid_registration_sends_one_email_with_the_invoice_and_alerts_super_admins_only(): void
    {
        NotificationFacade::fake();
        $mentor = $this->person('instructor');
        $learner = $this->person('student');
        $superOne = $this->person('super_admin');
        $superTwo = $this->person('super_admin');
        $plainAdmin = $this->person('admin');
        $cohort = $this->cohort(650, $mentor);
        $payment = $this->pendingPayment($learner, $cohort);

        $this->payWebhook()->assertStatus(200);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->invoice_number);

        // The learner: in-app and exactly one email (payment + registration + invoice).
        $this->assertSame(1, $this->inApp($learner, 'payment'));
        NotificationFacade::assertSentToTimes($learner, PaymentReceivedNotification::class, 1);
        NotificationFacade::assertNotSentTo($learner, CourseRegistrationNotification::class);

        // Super admins: in-app and email. Plain admins: neither.
        foreach ([$superOne, $superTwo] as $superAdmin) {
            $this->assertSame(1, $this->inApp($superAdmin, 'payment'));
            NotificationFacade::assertSentTo($superAdmin, NewPaymentAdminNotification::class);
        }
        $this->assertSame(0, $this->inApp($plainAdmin));
        NotificationFacade::assertNotSentTo($plainAdmin, NewPaymentAdminNotification::class);

        // The cohort's mentor hears a learner joined.
        $this->assertSame(1, $this->inApp($mentor, 'enrollment'));
    }

    public function test_a_replayed_payment_webhook_does_not_notify_twice(): void
    {
        NotificationFacade::fake();
        $learner = $this->person('student');
        $superAdmin = $this->person('super_admin');
        $this->pendingPayment($learner, $this->cohort(650));

        $this->payWebhook();
        $this->payWebhook();

        $this->assertSame(1, $this->inApp($learner, 'payment'));
        $this->assertSame(1, $this->inApp($superAdmin, 'payment'));
        NotificationFacade::assertSentToTimes($learner, PaymentReceivedNotification::class, 1);
    }

    public function test_finishing_every_lesson_congratulates_the_learner_once(): void
    {
        $learner = $this->person('student');
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id, 'enrollment_status' => 'active']);
        $module = CourseModule::factory()->create(['course_id' => $enrollment->course_id]);
        $first = CourseLesson::factory()->create(['module_id' => $module->id]);
        $second = CourseLesson::factory()->create(['module_id' => $module->id]);
        Sanctum::actingAs($learner);

        $this->postJson('/api/lesson-progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $first->id, 'status' => 'completed'])->assertStatus(201);
        $this->assertSame(0, $this->inApp($learner), 'not finished yet');

        $this->postJson('/api/lesson-progress', ['enrollment_id' => $enrollment->id, 'lesson_id' => $second->id, 'status' => 'completed'])->assertStatus(201);
        $this->assertSame(1, $this->inApp($learner, 'enrollment'));
        $this->assertStringContainsString('completed', InApp::where('user_id', $learner->id)->value('message'));
    }

    public function test_changing_a_cohorts_dates_tells_its_learners_and_mentors_but_a_price_change_does_not(): void
    {
        $admin = $this->person('admin');
        $mentor = $this->person('instructor');
        $learner = $this->person('student');
        $dropped = $this->person('student');
        $other = $this->person('student');
        $cohort = $this->cohort(0, $mentor);
        Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id, 'enrollment_status' => 'active']);
        Enrollment::factory()->create(['learner_id' => $dropped->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id, 'enrollment_status' => 'dropped']);
        Sanctum::actingAs($admin);

        // The cohort form always sends the whole record.
        $full = fn (array $changes) => array_merge([
            'course_id' => $cohort->course_id, 'label' => $cohort->label, 'mode' => $cohort->mode, 'capacity' => 30, 'status' => 'open',
            'location_city' => $cohort->location_city, 'location_country' => $cohort->location_country,
            'price' => 0, 'start_date' => $cohort->start_date->toDateString(), 'end_date' => $cohort->end_date?->toDateString(),
        ], $changes);

        $this->patchJson("/api/cohorts/{$cohort->id}", $full(['price' => 99]))->assertStatus(200);
        $this->assertSame(0, $this->inApp($learner), 'a price change is not a schedule change');

        $this->patchJson("/api/cohorts/{$cohort->id}", $full([
            'start_date' => now()->addWeeks(3)->toDateString(), 'end_date' => now()->addWeeks(8)->toDateString(),
        ]))->assertStatus(200);

        $this->assertSame(1, $this->inApp($learner, 'enrollment'));
        $this->assertSame(1, $this->inApp($mentor, 'mentor_application'));
        $this->assertSame(0, $this->inApp($dropped), 'dropped learners are not bothered');
        $this->assertSame(0, $this->inApp($other));
        $this->assertSame(0, $this->inApp($admin), 'the editor is not told about their own edit');
    }

    // ── mentors ───────────────────────────────────────────────────────────────

    public function test_approving_a_mentor_application_emails_the_mentor_but_rejecting_only_notifies(): void
    {
        NotificationFacade::fake();
        $superAdmin = $this->person('super_admin');
        $mentor = $this->person('instructor');
        $cohort = $this->cohort(0);
        $approved = CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'pending']);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/cohort-mentor-applications/{$approved->id}/approval-status", ['status' => 'approved'])->assertStatus(200);
        $this->assertSame(1, $this->inApp($mentor, 'mentor_application'));
        NotificationFacade::assertSentTo($mentor, MentorApplicationApprovedNotification::class);

        $other = $this->person('instructor');
        $rejected = CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $other->id, 'status' => 'pending']);
        $this->patchJson("/api/cohort-mentor-applications/{$rejected->id}/approval-status", ['status' => 'rejected', 'rejection_reason' => 'Not a fit'])->assertStatus(200);
        $this->assertSame(1, $this->inApp($other, 'mentor_application'));
        NotificationFacade::assertNotSentTo($other, MentorApplicationApprovedNotification::class);
    }

    public function test_the_outcome_of_an_account_review_is_emailed_to_the_mentor(): void
    {
        NotificationFacade::fake();
        $superAdmin = $this->person('super_admin');
        Sanctum::actingAs($superAdmin);

        foreach (['approved' => true, 'banned' => false] as $status => $approved) {
            $mentor = $this->person('instructor');
            $profile = InstructorProfile::factory()->create(['user_id' => $mentor->id, 'approval_status' => 'pending']);

            $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", ['approval_status' => $status])->assertStatus(200);

            $this->assertSame(1, $this->inApp($mentor, 'instructor_approval'));
            NotificationFacade::assertSentTo($mentor, InstructorAccountDecisionNotification::class, fn ($n) => $n->approved === $approved);
        }
    }

    // ── admins ────────────────────────────────────────────────────────────────

    public function test_review_outcomes_reach_plain_admins_not_the_decider_or_mentors_and_send_no_email(): void
    {
        NotificationFacade::fake();
        $superAdmin = $this->person('super_admin');
        $author = $this->person('admin');
        $mentor = $this->person('instructor');
        $cohort = $this->cohort(0, $mentor);
        $module = CourseModule::factory()->create(['course_id' => $cohort->course_id, 'admin_approval_status' => 'pending']);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/course-modules/{$module->id}/approval-status", ['admin_approval_status' => 'rejected', 'admin_rejection_reason' => 'Too thin'])->assertStatus(200);

        $this->assertSame(1, $this->inApp($author, 'module_review'));
        $this->assertStringContainsString('Too thin', InApp::where('user_id', $author->id)->value('message'));
        $this->assertSame(0, $this->inApp($superAdmin));
        $this->assertSame(0, $this->inApp($mentor), 'mentors do not author course content');
        NotificationFacade::assertNothingSent();
    }

    public function test_a_brochure_request_alerts_super_admins_by_email_and_staff_in_app(): void
    {
        NotificationFacade::fake();
        $superAdmin = $this->person('super_admin');
        $plainAdmin = $this->person('admin');
        $course = Course::factory()->create(['status' => 'published', 'admin_approval_status' => 'approved']);

        $this->postJson("/api/courses/{$course->id}/brochure-requests", ['full_name' => 'Wanjiru K', 'email' => 'w@example.com', 'phone' => '+254700123456'])
            ->assertStatus(201);

        NotificationFacade::assertSentTo($superAdmin, NewBrochureRequestAdminNotification::class);
        NotificationFacade::assertNotSentTo($plainAdmin, NewBrochureRequestAdminNotification::class);
        $this->assertSame(1, $this->inApp($superAdmin, 'brochure_request'));
        $this->assertSame(1, $this->inApp($plainAdmin, 'brochure_request'));
    }

    public function test_an_approved_brochure_request_emails_the_requester(): void
    {
        NotificationFacade::fake();
        $superAdmin = $this->person('super_admin');
        $course = Course::factory()->create(['brochure_url' => '/storage/course-materials/b.pdf']);
        $lead = CourseLead::factory()->create(['course_id' => $course->id, 'source' => 'brochure', 'brochure_status' => 'pending', 'email' => 'w@example.com']);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'sent'])->assertStatus(200);

        NotificationFacade::assertSentOnDemand(CourseBrochureNotification::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'w@example.com');
        $this->assertSame('sent', $lead->fresh()->brochure_status);
    }

    // ── how the emails look ───────────────────────────────────────────────────

    public function test_every_email_has_the_logo_header_policy_links_and_contact_footer(): void
    {
        $learner = $this->person('student', ['name' => 'Amina Otieno']);
        $mentor = $this->person('instructor', ['name' => 'Baraka Mentor']);
        $cohort = $this->cohort(650, $mentor);
        $payment = $this->pendingPayment($learner, $cohort);
        $payment->update(['status' => 'completed', 'paid_at' => now(), 'invoice_number' => 'INV-2026-00042', 'channel' => 'card']);
        $enrollment = Enrollment::factory()->create(['learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id]);
        $application = CohortMentorApplication::factory()->approved()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id]);
        $lead = CourseLead::factory()->create(['course_id' => $cohort->course_id, 'source' => 'brochure']);

        $emails = [
            'welcome' => new WelcomeNotification('Amina'),
            'otp' => new OtpVerificationNotification('amina@example.com'),
            'reset' => new ResetPasswordNotification('amina@example.com'),
            'registration' => new CourseRegistrationNotification($enrollment),
            'payment' => new PaymentReceivedNotification($payment),
            'mentor approved' => new MentorApplicationApprovedNotification($application),
            'account approved' => new InstructorAccountDecisionNotification('Baraka', true),
            'account declined' => new InstructorAccountDecisionNotification('Baraka', false),
            'admin payment' => new NewPaymentAdminNotification($payment),
            'admin brochure' => new NewBrochureRequestAdminNotification($lead),
            'brochure' => new CourseBrochureNotification($cohort->course, 'Amina', 'https://giseafrica.test/b.pdf'),
            'role changed' => new RoleChangedNotification('Amina', 'student', 'admin'),
        ];

        foreach ($emails as $name => $notification) {
            $html = (string) $notification->toMail($learner)->render();

            $this->assertStringContainsString('https://giseafrica.test/brand/logo-email.png', $html, "{$name}: logo");
            foreach (['privacy-policy', 'terms-of-use', 'cookie-policy'] as $policy) {
                $this->assertStringContainsString("https://giseafrica.test/policies/{$policy}", $html, "{$name}: {$policy} link");
            }
            $this->assertStringNotContainsString('payments-refund-policy', $html, "{$name}: payments & refunds is not linked from emails");
            $this->assertStringContainsString('info@giseafrica.com', $html, "{$name}: contact");
            $this->assertStringContainsString('Bimz Plaza', $html, "{$name}: address");
        }
    }

    public function test_the_payment_email_attaches_the_invoice_pdf(): void
    {
        $learner = $this->person('student');
        $payment = $this->pendingPayment($learner, $this->cohort(650));
        $payment->update(['status' => 'completed', 'paid_at' => now(), 'channel' => 'card']);

        $mail = (new PaymentReceivedNotification($payment))->toMail($learner);

        $this->assertCount(1, $mail->rawAttachments);
        $attachment = $mail->rawAttachments[0];
        $this->assertStringStartsWith('%PDF', $attachment['data']);
        $this->assertMatchesRegularExpression('/^GISE-Africa-INV-\d{4}-\d{5}\.pdf$/', $attachment['name']);
        $this->assertStringContainsString('USD 650', (string) $mail->render());
    }

    // ── role changes ──────────────────────────────────────────────────────────

    public function test_changing_someones_role_notifies_them_and_the_admin_who_did_it_and_emails_them(): void
    {
        NotificationFacade::fake();
        $actor = $this->person('super_admin');
        $bystander = $this->person('super_admin');
        $target = $this->person('student', ['name' => 'Amina Otieno']);
        Sanctum::actingAs($actor);

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'admin'])->assertStatus(200);

        $this->assertSame('admin', ScholarUser::find($target->id)->role);
        $this->assertSame(1, $this->inApp($target, 'system'));
        $this->assertStringContainsString('an admin', InApp::where('user_id', $target->id)->value('message'));
        $this->assertSame(1, $this->inApp($actor, 'system'));
        $this->assertStringContainsString("Amina Otieno's role from a student to an admin", InApp::where('user_id', $actor->id)->value('message'));
        $this->assertSame(0, $this->inApp($bystander), 'other super admins are not pinged');

        NotificationFacade::assertSentTo($target, RoleChangedNotification::class, fn ($n) => $n->previousRole === 'student' && $n->newRole === 'admin');
        NotificationFacade::assertNotSentTo($actor, RoleChangedNotification::class);
    }

    public function test_changing_your_own_role_is_one_notification_and_an_unchanged_role_is_none(): void
    {
        NotificationFacade::fake();
        $actor = $this->person('super_admin');
        $other = $this->person('super_admin');
        Sanctum::actingAs($actor);

        $this->patchJson("/api/scholar-users/{$other->id}/role", ['role' => 'super_admin'])->assertStatus(200)->assertJsonPath('message', 'No change.');
        $this->assertSame(0, $this->inApp($other));
        NotificationFacade::assertNotSentTo($other, RoleChangedNotification::class);

        // Stepping down yourself (allowed while another super admin exists) is one event, not two.
        $this->patchJson("/api/scholar-users/{$actor->id}/role", ['role' => 'admin'])->assertStatus(200);
        $this->assertSame(1, $this->inApp($actor), 'you are told once, not as both parties');
    }

    public function test_a_role_change_is_saved_even_if_the_email_cannot_be_sent(): void
    {
        $this->breakMail();
        $actor = $this->person('super_admin');
        $target = $this->person('student');
        Sanctum::actingAs($actor);

        $this->patchJson("/api/scholar-users/{$target->id}/role", ['role' => 'instructor'])->assertStatus(200);

        $this->assertSame('instructor', ScholarUser::find($target->id)->role);
        $this->assertSame(1, $this->inApp($target), 'the in-app notice does not depend on email');
        $this->assertSame(1, $this->inApp($actor));
    }

    // ── course updates ────────────────────────────────────────────────────────

    private function courseEdit(Course $course, array $changes): array
    {
        return array_merge($course->only(['title', 'code', 'slug', 'price']), $changes);
    }

    public function test_updating_a_course_notifies_the_other_super_admins_in_app_only(): void
    {
        NotificationFacade::fake();
        $editor = $this->person('admin', ['name' => 'Edwin Editor']);
        $superOne = $this->person('super_admin');
        $superTwo = $this->person('super_admin');
        $mentor = $this->person('instructor');
        $course = Course::factory()->create(['price' => 100, 'title' => 'Data Basics']);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/courses/{$course->id}", $this->courseEdit($course, ['price' => 250]))->assertStatus(200);

        foreach ([$superOne, $superTwo] as $superAdmin) {
            $this->assertSame(1, $this->inApp($superAdmin, 'course_review'));
        }
        $message = InApp::where('user_id', $superOne->id)->value('message');
        $this->assertStringContainsString('Edwin Editor updated the course "Data Basics" (price)', $message);
        $this->assertSame("/admin/courses/{$course->id}", InApp::where('user_id', $superOne->id)->value('link'));

        $this->assertSame(0, $this->inApp($editor), 'the editor is not told about their own edit');
        $this->assertSame(0, $this->inApp($mentor));
        NotificationFacade::assertNothingSent(); // no email for course updates
    }

    public function test_a_super_admins_own_edit_tells_only_the_other_super_admins(): void
    {
        $editor = $this->person('super_admin');
        $other = $this->person('super_admin');
        $course = Course::factory()->create(['price' => 100]);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/courses/{$course->id}", $this->courseEdit($course, ['price' => 120]))->assertStatus(200);

        $this->assertSame(1, $this->inApp($other, 'course_review'));
        $this->assertSame(0, $this->inApp($editor));
    }

    public function test_saving_a_course_without_changing_anything_creates_no_notification(): void
    {
        $editor = $this->person('admin');
        $superAdmin = $this->person('super_admin');
        $course = Course::factory()->create();
        Sanctum::actingAs($editor);

        $this->patchJson("/api/courses/{$course->id}", $this->courseEdit($course, []))->assertStatus(200);

        $this->assertSame(0, $this->inApp($superAdmin));
    }

    public function test_the_notice_lists_what_changed_in_plain_words(): void
    {
        $editor = $this->person('admin');
        $superAdmin = $this->person('super_admin');
        $course = Course::factory()->create(['price' => 100, 'status' => 'draft', 'tagline' => 'Old']);
        Sanctum::actingAs($editor);

        $this->patchJson("/api/courses/{$course->id}", $this->courseEdit($course, ['price' => 300, 'status' => 'published', 'tagline' => 'New']))->assertStatus(200);

        $message = InApp::where('user_id', $superAdmin->id)->value('message');
        foreach (['price', 'status', 'tagline'] as $field) {
            $this->assertStringContainsString($field, $message);
        }
        $this->assertStringNotContainsString('published_at', $message, 'bookkeeping columns are not listed');
    }

    // ── deliverability ────────────────────────────────────────────────────────

    public function test_every_email_also_goes_out_with_a_plain_text_version(): void
    {
        $user = $this->person('student', ['name' => 'Amina Otieno']);
        app('mail.manager')->purge('array');

        $user->notifyNow(new WelcomeNotification('Amina'));
        $user->notifyNow(new OtpVerificationNotification('amina@example.com'));

        $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);

        foreach ($sent as $delivery) {
            $email = $delivery->getOriginalMessage();
            $text = $email->getTextBody();

            $this->assertNotEmpty($email->getHtmlBody(), 'the branded HTML is still sent');
            $this->assertNotEmpty($text, 'and now a plain-text version alongside it');
            $this->assertStringNotContainsString('<', $text, 'no markup leaks into the text');
            $this->assertStringNotContainsString('display:none', $text);
            $this->assertStringContainsString('info@giseafrica.com', $text);
            $this->assertStringContainsString('https://giseafrica.test/policies/privacy-policy', $text, 'links keep their address');
        }

        // The code itself is readable in the text version.
        $otpText = $sent[1]->getOriginalMessage()->getTextBody();
        $this->assertMatchesRegularExpression('/\b\d{6}\b/', $otpText);
    }

    public function test_the_text_converter_keeps_links_and_drops_hidden_preview_text(): void
    {
        $text = \App\Listeners\AddPlainTextAlternative::toText(
            '<html><head><style>p{color:red}</style></head><body><div style="display:none;">Hidden preview</div>'
            . '<p>Hello &amp; welcome</p><p><a href="https://giseafrica.com/courses">Browse courses</a> or '
            . '<a href="mailto:info@giseafrica.com">email us</a></p><table><tr><td>Course</td><td>GIS</td></tr></table></body></html>'
        );

        $this->assertStringContainsString('Hello & welcome', $text);
        $this->assertStringContainsString('Browse courses (https://giseafrica.com/courses)', $text);
        $this->assertStringContainsString('email us', $text);
        $this->assertStringNotContainsString('mailto:', $text);
        $this->assertStringNotContainsString('Hidden preview', $text);
        $this->assertStringNotContainsString('color:red', $text);
        $this->assertStringContainsString('Course: GIS', $text);
    }

    // ── a broken mail server breaks nothing and falsifies nothing ─────────────

    public function test_signup_still_succeeds_when_the_mail_server_is_down(): void
    {
        $this->breakMail();

        $this->postJson('/api/auth/signup', [
            'first_name' => 'Amina', 'last_name' => 'Otieno', 'email' => 'amina@example.com', 'phone' => '+254700111222',
            'password' => 'Test@12345', 'confirmPassword' => 'Test@12345', 'role' => 'student',
        ])->assertStatus(200)->assertJsonStructure(['token']);

        $this->assertDatabaseHas('users', ['email' => 'amina@example.com']);
        $this->assertDatabaseHas('scholar_users', ['email' => 'amina@example.com']);
    }

    public function test_a_payment_is_still_recorded_and_the_learner_enrolled_when_the_mail_server_is_down(): void
    {
        $this->breakMail();
        $mentor = $this->person('instructor');
        $learner = $this->person('student');
        $superAdmin = $this->person('super_admin');
        $cohort = $this->cohort(650, $mentor);
        $payment = $this->pendingPayment($learner, $cohort);

        $this->payWebhook()->assertStatus(200);

        $this->assertSame('completed', $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->invoice_number);
        $this->assertDatabaseHas('enrollments', ['learner_id' => $learner->id, 'cohort_id' => $cohort->id]);
        // The in-app notices are separate from email and still land.
        $this->assertSame(1, $this->inApp($learner, 'payment'));
        $this->assertSame(1, $this->inApp($superAdmin, 'payment'));
        $this->assertSame(1, $this->inApp($mentor, 'enrollment'));
    }

    public function test_registration_and_approvals_still_work_when_the_mail_server_is_down(): void
    {
        $this->breakMail();
        $superAdmin = $this->person('super_admin');
        $learner = $this->person('student');
        $mentor = $this->person('instructor');
        $cohort = $this->cohort(0);

        Sanctum::actingAs($learner);
        $this->postJson('/api/enrollments', ['learner_id' => $learner->id, 'course_id' => $cohort->course_id, 'cohort_id' => $cohort->id])->assertStatus(201);
        $this->assertDatabaseHas('enrollments', ['learner_id' => $learner->id]);

        Sanctum::actingAs($superAdmin);
        $profile = InstructorProfile::factory()->create(['user_id' => $mentor->id, 'approval_status' => 'pending']);
        $this->patchJson("/api/instructor-profiles/{$profile->id}/approval-status", ['approval_status' => 'approved'])->assertStatus(200);
        $this->assertSame('approved', $profile->fresh()->approval_status);

        $application = CohortMentorApplication::factory()->create(['cohort_id' => $cohort->id, 'instructor_id' => $mentor->id, 'status' => 'pending']);
        $this->patchJson("/api/cohort-mentor-applications/{$application->id}/approval-status", ['status' => 'approved'])->assertStatus(200);
        $this->assertSame('approved', $application->fresh()->status);
    }

    public function test_a_brochure_request_is_still_taken_when_the_mail_server_is_down(): void
    {
        $this->breakMail();
        $this->person('super_admin');
        $course = Course::factory()->create(['status' => 'published', 'admin_approval_status' => 'approved']);

        $this->postJson("/api/courses/{$course->id}/brochure-requests", ['full_name' => 'Wanjiru K', 'email' => 'w@example.com', 'phone' => '+254700123456'])
            ->assertStatus(201);

        $this->assertDatabaseHas('course_leads', ['email' => 'w@example.com', 'brochure_status' => 'pending']);
    }

    public function test_a_brochure_is_not_marked_sent_when_the_email_could_not_be_sent(): void
    {
        $this->breakMail();
        $superAdmin = $this->person('super_admin');
        $course = Course::factory()->create(['brochure_url' => '/storage/course-materials/b.pdf']);
        $lead = CourseLead::factory()->create(['course_id' => $course->id, 'source' => 'brochure', 'brochure_status' => 'pending']);
        Sanctum::actingAs($superAdmin);

        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'sent'])
            ->assertStatus(502)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'still pending'));

        $lead->refresh();
        $this->assertSame('pending', $lead->brochure_status);
        $this->assertNull($lead->brochure_sent_at);
        $this->assertNull($lead->reviewed_by);
        $this->assertDatabaseMissing('admin_audit_logs', ['action' => 'send_brochure', 'target_id' => $lead->id]);

        // Declining sends nothing, so it still works.
        $this->patchJson("/api/course-leads/{$lead->id}/brochure-status", ['status' => 'declined'])->assertStatus(200);
        $this->assertSame('declined', $lead->fresh()->brochure_status);
    }

    public function test_a_code_that_could_not_be_emailed_is_discarded_and_the_caller_is_told(): void
    {
        $this->breakMail();
        $user = $this->person('student', ['email' => 'amina@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'amina@example.com'])->assertStatus(503);
        $this->assertSame(0, OtpRow::where('identifier', 'amina@example.com')->count(), 'no valid reset code is left behind');

        Sanctum::actingAs($user);
        $this->postJson('/api/auth/verify-email', ['email' => 'amina@example.com'])->assertStatus(503);
        $this->assertSame(0, OtpRow::where('identifier', 'amina@example.com')->count());
    }

    public function test_a_code_is_kept_when_the_email_does_go_out(): void
    {
        $this->person('student', ['email' => 'amina@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'amina@example.com'])->assertStatus(200);

        $this->assertSame(1, OtpRow::where('identifier', 'amina@example.com')->count());
    }
}

/** A mail transport that always fails, like an unreachable SMTP server. */
class ExplodingTransport extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        throw new TransportException('Connection to the mail server timed out.');
    }

    public function __toString(): string
    {
        return 'exploding://';
    }
}
