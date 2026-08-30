<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\ParticipateInTravelQuizAction;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours : CRUD quiz + questions,
 * participation (score serveur, participation unique), résultats.
 * Cross-tenant → 404 sûr.
 */
class TravelQuizController extends Controller
{
    // ── Quiz ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelQuiz::class)) {
            abort(403);
        }

        $query = TravelQuiz::query()->where('company_id', $actor->company_id);

        $status = (string) $request->query('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $perPage = max(1, min(1000, (int) $request->query('per_page', 50)));

        $quizzes = $query->orderByDesc('id')->paginate($perPage);

        return new JsonResponse(['data' => $quizzes->items(), 'meta' => [
            'total' => $quizzes->total(),
            'per_page' => $quizzes->perPage(),
            'current_page' => $quizzes->currentPage(),
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelQuiz::class)) {
            abort(403);
        }

        $title = trim((string) $request->json('title'));
        $maxAttempts = (int) $request->json('max_attempts', 1);
        $status = (string) $request->json('status', TravelQuiz::STATUS_DRAFT);

        if ($title === '' || mb_strlen($title) > 200) {
            abort(422, 'Title is required (max 200 characters).');
        }

        if ($maxAttempts < 1 || $maxAttempts > 100) {
            abort(422, 'max_attempts must be between 1 and 100.');
        }

        if (! in_array($status, [TravelQuiz::STATUS_DRAFT, TravelQuiz::STATUS_PUBLISHED, TravelQuiz::STATUS_ARCHIVED], true)) {
            abort(422, 'Invalid quiz status.');
        }

        $quiz = TravelQuiz::query()->create([
            'company_id' => $actor->company_id,
            'title' => $title,
            'description_redacted' => $request->json('description_redacted'),
            'status' => $status,
            'max_attempts' => $maxAttempts,
            'published_at' => $status === TravelQuiz::STATUS_PUBLISHED ? now() : null,
        ]);

        return new JsonResponse(['data' => $quiz], 201);
    }

    public function show(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('view', $travelQuiz)) {
            abort(403);
        }

        return new JsonResponse(['data' => $travelQuiz->load('questions')]);
    }

    public function update(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $data = [];

        if ($request->json('title') !== null) {
            $title = trim((string) $request->json('title'));
            if ($title === '' || mb_strlen($title) > 200) {
                abort(422, 'Title is required (max 200 characters).');
            }
            $data['title'] = $title;
        }

        if ($request->json('description_redacted') !== null) {
            $data['description_redacted'] = $request->json('description_redacted');
        }

        if ($request->json('max_attempts') !== null) {
            $maxAttempts = (int) $request->json('max_attempts');
            if ($maxAttempts < 1 || $maxAttempts > 100) {
                abort(422, 'max_attempts must be between 1 and 100.');
            }
            $data['max_attempts'] = $maxAttempts;
        }

        if ($request->json('status') !== null) {
            $status = (string) $request->json('status');
            if (! in_array($status, [TravelQuiz::STATUS_DRAFT, TravelQuiz::STATUS_PUBLISHED, TravelQuiz::STATUS_ARCHIVED], true)) {
                abort(422, 'Invalid quiz status.');
            }
            $data['status'] = $status;
            if ($status === TravelQuiz::STATUS_PUBLISHED) {
                $data['published_at'] = $travelQuiz->published_at ?? now();
            }
        }

        $travelQuiz->update($data);

        return new JsonResponse(['data' => $travelQuiz->refresh()]);
    }

    public function destroy(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('delete', $travelQuiz)) {
            abort(403);
        }

        $travelQuiz->delete();

        return new JsonResponse(null, 204);
    }

    // ── Questions (sous-ressource) ─────────────────────────────────────────

    public function storeQuestion(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $question = trim((string) $request->json('question'));
        $options = $request->json('options');
        $correctIndex = (int) $request->json('correct_option_index', -1);
        $points = (int) $request->json('points', 1);
        $sortOrder = (int) $request->json('sort_order', 0);

        if ($question === '' || mb_strlen($question) > 500) {
            abort(422, 'Question is required (max 500 characters).');
        }

        if (! is_array($options) || count($options) < 2 || count($options) > 10) {
            abort(422, 'Options must be an array of 2 to 10 choices.');
        }

        foreach ($options as $option) {
            if (! is_string($option) || trim($option) === '') {
                abort(422, 'Each option must be a non-empty string.');
            }
        }

        if ($correctIndex < 0 || $correctIndex >= count($options)) {
            abort(422, 'correct_option_index is out of range.');
        }

        if ($points < 1 || $points > 1000) {
            abort(422, 'Points must be between 1 and 1000.');
        }

        $questionRow = TravelQuizQuestion::query()->create([
            'company_id' => $actor->company_id,
            'quiz_id' => $travelQuiz->id,
            'question' => $question,
            'options' => array_values($options),
            'correct_option_index' => $correctIndex,
            'points' => $points,
            'sort_order' => $sortOrder,
        ]);

        return new JsonResponse(['data' => $questionRow], 201);
    }

    public function updateQuestion(Request $request, TravelQuiz $travelQuiz, TravelQuizQuestion $travelQuizQuestion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id || $travelQuizQuestion->quiz_id !== $travelQuiz->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $data = [];

        if ($request->json('question') !== null) {
            $question = trim((string) $request->json('question'));
            if ($question === '' || mb_strlen($question) > 500) {
                abort(422, 'Question is required (max 500 characters).');
            }
            $data['question'] = $question;
        }

        if ($request->json('options') !== null) {
            $options = $request->json('options');
            if (! is_array($options) || count($options) < 2 || count($options) > 10) {
                abort(422, 'Options must be an array of 2 to 10 choices.');
            }
            $data['options'] = array_values($options);
        }

        if ($request->json('correct_option_index') !== null) {
            $correctIndex = (int) $request->json('correct_option_index');
            $optionCount = count((array) ($data['options'] ?? $travelQuizQuestion->options));
            if ($correctIndex < 0 || $correctIndex >= $optionCount) {
                abort(422, 'correct_option_index is out of range.');
            }
            $data['correct_option_index'] = $correctIndex;
        }

        if ($request->json('points') !== null) {
            $points = (int) $request->json('points');
            if ($points < 1 || $points > 1000) {
                abort(422, 'Points must be between 1 and 1000.');
            }
            $data['points'] = $points;
        }

        if ($request->json('sort_order') !== null) {
            $data['sort_order'] = (int) $request->json('sort_order');
        }

        $travelQuizQuestion->update($data);

        return new JsonResponse(['data' => $travelQuizQuestion->refresh()]);
    }

    public function destroyQuestion(Request $request, TravelQuiz $travelQuiz, TravelQuizQuestion $travelQuizQuestion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id || $travelQuizQuestion->quiz_id !== $travelQuiz->id) {
            abort(404);
        }

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $travelQuizQuestion->delete();

        return new JsonResponse(null, 204);
    }

    // ── Participation & résultats ─────────────────────────────────────────

    public function participate(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('participate', $travelQuiz)) {
            abort(403);
        }

        $answers = $request->json('answers');

        if (! is_array($answers)) {
            abort(422, 'Answers must be an array.');
        }

        $participation = app(ParticipateInTravelQuizAction::class)->execute(
            $travelQuiz,
            $actor,
            array_map(static fn ($v): int => (int) $v, $answers),
        );

        return new JsonResponse(['data' => [
            'id' => $participation->id,
            'quiz_id' => $participation->quiz_id,
            'score' => $participation->score,
            'status' => $participation->status,
            'completed_at' => $participation->completed_at,
        ]], 201);
    }

    public function participations(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->company_id !== $travelQuiz->company_id) {
            abort(404);
        }

        if ($actor->cannot('manage', $travelQuiz)) {
            abort(403);
        }

        $rows = TravelQuizParticipation::query()
            ->where('company_id', $actor->company_id)
            ->where('quiz_id', $travelQuiz->id)
            ->orderByDesc('score')
            ->get(['id', 'quiz_id', 'participant_type', 'participant_id', 'score', 'status', 'completed_at']);

        return new JsonResponse(['data' => $rows]);
    }
}
