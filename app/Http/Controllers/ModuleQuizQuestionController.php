<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Helpers\Validations;
use App\Models\ModuleQuiz;
use App\Models\ModuleQuizQuestion;
use App\Models\ScholarUser;
use App\Services\NotificationService;
use App\Traits\AuthorizesCourseOwnership;

class ModuleQuizQuestionController extends Controller
{
    use AuthorizesCourseOwnership;

    public function index(Request $request)
    {
        try {
            // This route has no auth:sanctum middleware (it's intentionally public -
            // see routes/api.php), so $request->user() is never populated here even
            // with a valid Bearer token. Resolve the "sanctum" guard directly so an
            // authenticated instructor/admin is still recognized over real HTTP,
            // while the endpoint stays reachable without a token for everyone else.
            $authUser = $request->user('sanctum');
            $user = $authUser ? ScholarUser::find($authUser->id) : null;
            $isAdminOrInstructor = $user && in_array($user->role, ['instructor', 'admin', 'super_admin']);

            $query = ModuleQuizQuestion::query();

            if ($quizId = trim($request->input('quiz_id', ''))) {
                $query->where('quiz_id', $quizId);
            }

            $results = $query->orderBy('order_index', 'asc')->paginate(30);

            if (!$isAdminOrInstructor) {
                $results->getCollection()->makeHidden('correct_option_key');
            } else {
                $results->getCollection()->makeVisible('correct_option_key');
            }

            return response()->json([
                'status' => 200,
                'data'   => $results,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@index: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while retrieving module quiz questions.',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateModuleQuizQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $data = $request->all();

        if (!array_key_exists($data['correct_option_key'], $data['options'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['correct_option_key' => ['Must match one of the provided option keys.']],
            ], 422);
        }

        $quiz = ModuleQuiz::find($data['quiz_id']);

        if (!$this->canManageCourse($request, $quiz?->module?->course)) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $question = ModuleQuizQuestion::create($data);
            $question->refresh();
            $question->makeVisible('correct_option_key');

            $this->syncQuizApprovalAfterQuestionChange($user, $quiz);

            return response()->json([
                'status'  => 201,
                'message' => 'Module quiz question created successfully.',
                'data'    => $question,
            ], 201);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@store: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while creating the module quiz question.',
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        $validator = Validations::validateModuleQuizQuestion($request->all());

        if ($validator->fails()) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->messages(),
            ], 422);
        }

        $data = $request->all();

        if (!array_key_exists($data['correct_option_key'], $data['options'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => ['correct_option_key' => ['Must match one of the provided option keys.']],
            ], 422);
        }

        try {
            $question = ModuleQuizQuestion::find($id);

            if (!$question) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz question not found.',
                ], 404);
            }

            $user = $request->scholarUser();

            if (!$this->canManageCourse($request, $question->quiz?->module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            if (!$user->isAdmin()) {
                unset($data['quiz_id']);
            }

            $question->update($data);
            $question->makeVisible('correct_option_key');

            $this->syncQuizApprovalAfterQuestionChange($user, $question->quiz);

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz question updated successfully.',
                'data'    => $question,
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@update: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while updating the module quiz question.',
            ], 500);
        }
    }

    public function delete(Request $request, string $id)
    {
        $user = $request->scholarUser();

        if (!$user || !in_array($user->role, ['instructor', 'admin', 'super_admin'])) {
            return response()->json([
                'status'  => 403,
                'message' => 'Forbidden.',
            ], 403);
        }

        try {
            $question = ModuleQuizQuestion::find($id);

            if (!$question) {
                return response()->json([
                    'status'  => 404,
                    'message' => 'Module quiz question not found.',
                ], 404);
            }

            $quiz = $question->quiz;

            if (!$this->canManageCourse($request, $quiz?->module?->course)) {
                return response()->json([
                    'status'  => 403,
                    'message' => 'Forbidden.',
                ], 403);
            }

            $question->delete();

            $this->syncQuizApprovalAfterQuestionChange($user, $quiz);

            return response()->json([
                'status'  => 200,
                'message' => 'Module quiz question deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error('ModuleQuizQuestionController@delete: ' . $e->getMessage());
            return response()->json([
                'status'  => 500,
                'message' => 'An error occurred while deleting the module quiz question.',
            ], 500);
        }
    }

    /**
     * An instructor changing a quiz's questions sends the parent quiz back
     * for re-review; an admin's own change on a non-approved quiz implicitly
     * re-approves it, since an admin editing their own review shouldn't
     * require a separate approval click.
     */
    private function syncQuizApprovalAfterQuestionChange(ScholarUser $user, ?ModuleQuiz $quiz): void
    {
        if (!$quiz) {
            return;
        }

        if ($user->isSuperAdmin()) {
            if ($quiz->admin_approval_status !== 'approved') {
                $quiz->forceFill(['admin_approval_status' => 'approved', 'admin_rejection_reason' => null])->save();
            }
            return;
        }

        $quiz->forceFill(['admin_approval_status' => 'pending', 'admin_rejection_reason' => null])->save();

        NotificationService::notifySuperAdmins(
            'quiz_review',
            "{$user->email} changed a question on the quiz \"{$quiz->title}\", which needs re-review."
        );
    }
}
