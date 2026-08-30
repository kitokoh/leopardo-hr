<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizParticipation;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use App\Modules\TravelAgency\Infrastructure\Services\TravelQuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours.
 *
 * CRUD quiz + questions (bonne réponse HACHÉE, jamais en clair au repos),
 * participation unique par (quiz, contact) — score calculé serveur,
 * résultats cohérents (score ≤ total).
 */
class TravelQuizController extends Controller
{
    // ── Quiz ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $quizzes = TravelQuiz::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->withCount('questions')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $quizzes->map(fn (TravelQuiz $q): array => $this->quizPayload($q))]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'max_participations_per_contact' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'bonus_points' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        $quiz = TravelQuiz::query()->create([
            'company_id' => $actor->company_id,
            'title' => $data['title'],
            'description_redacted' => $data['description'] ?? null,
            'status' => 'draft',
            'max_participations_per_contact' => $data['max_participations_per_contact'] ?? 1,
            'bonus_points' => $data['bonus_points'] ?? 0,
            'created_by_user_id' => $actor->id,
        ]);

        return response()->json(['data' => $this->quizPayload($quiz)])->setStatusCode(201);
    }

    public function show(Request $request, TravelQuiz $quiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quiz->company_id !== $actor->company_id) {
            abort(404);
        }

        $quiz->load('questions');

        return response()->json(['data' => $this->quizPayload($quiz, withQuestions: true)]);
    }

    public function publish(Request $request, TravelQuiz $quiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quiz->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        if ($quiz->questions()->count() === 0) {
            abort(422, 'Un quiz doit contenir au moins une question pour être publié.');
        }

        $quiz->forceFill(['status' => 'published'])->save();

        return response()->json(['data' => $this->quizPayload($quiz->refresh())]);
    }

    // ── Questions ───────────────────────────────────────────────────────────

    public function storeQuestion(Request $request, TravelQuiz $quiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quiz->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:500'],
            'choices' => ['required', 'array', 'min:2', 'max:6'],
            'choices.*' => ['required', 'string', 'max:200'],
            'correct_answer' => ['required', 'string', 'max:200'],
            'points' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ]);

        $rank = ((int) $quiz->questions()->max('rank')) + 1;

        $question = TravelQuizQuestion::query()->create([
            'company_id' => $actor->company_id,
            'quiz_id' => $quiz->id,
            'rank' => $rank,
            'label' => $data['label'],
            'choices' => $data['choices'],
            'correct_answer_hash' => hash('sha256', $data['correct_answer']),
            'points' => $data['points'] ?? 1,
        ]);

        return response()->json(['data' => $this->questionPayload($question)])->setStatusCode(201);
    }

    public function destroyQuestion(Request $request, TravelQuizQuestion $question): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($question->company_id !== $actor->company_id) {
            abort(404);
        }

        $this->denyUnlessManager($actor);

        $question->delete();

        return new JsonResponse(null, 204);
    }

    // ── Participation ───────────────────────────────────────────────────────

    public function participate(Request $request, TravelQuiz $quiz, TravelQuizService $service): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quiz->company_id !== $actor->company_id) {
            abort(404);
        }

        $data = $request->validate([
            'participant_identifier' => ['required', 'string', 'max:255'],
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:200'],
        ]);

        $participation = $service->participate(
            $quiz,
            $data['participant_identifier'],
            $data['answers'],
        );

        return response()->json(['data' => [
            'id' => $participation->id,
            'quiz_id' => $participation->quiz_id,
            'score' => $participation->score,
            'total_points' => $participation->total_points,
        ]])->setStatusCode(201);
    }

    public function results(Request $request, TravelQuiz $quiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quiz->company_id !== $actor->company_id) {
            abort(404);
        }

        $participations = TravelQuizParticipation::query()
            ->where('company_id', $actor->company_id)
            ->where('quiz_id', $quiz->id)
            ->orderByDesc('score')
            ->get();

        return response()->json(['data' => $participations->map(fn (TravelQuizParticipation $p): array => [
            'id' => $p->id,
            'participant_identifier' => $p->participant_identifier,
            'score' => $p->score,
            'total_points' => $p->total_points,
            'completed_at' => $p->completed_at->toIso8601String(),
        ])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function quizPayload(TravelQuiz $quiz, bool $withQuestions = false): array
    {
        return [
            'id' => $quiz->id,
            'title' => $quiz->title,
            'description' => $quiz->description_redacted,
            'status' => $quiz->status,
            'max_participations_per_contact' => $quiz->max_participations_per_contact,
            'bonus_points' => $quiz->bonus_points,
            'questions_count' => $quiz->questions_count ?? $quiz->questions()->count(),
            'questions' => $withQuestions
                ? $quiz->questions->map(fn (TravelQuizQuestion $q): array => $this->questionPayload($q))
                : null,
            'created_at' => $quiz->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionPayload(TravelQuizQuestion $question): array
    {
        return [
            'id' => $question->id,
            'quiz_id' => $question->quiz_id,
            'rank' => $question->rank,
            'label' => $question->label,
            'choices' => $question->choices,
            'points' => $question->points,
            // Jamais la bonne réponse en clair : seule la présence d'un hash.
            'has_correct_answer' => $question->correct_answer_hash !== '',
        ];
    }

    private function denyUnlessManager(Employee $actor): void
    {
        if (! $actor->hasManagerRole('principal', 'rh', 'manager')) {
            abort(403);
        }
    }
}
