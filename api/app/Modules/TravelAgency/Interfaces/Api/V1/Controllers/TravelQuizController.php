<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\TravelAgency\Application\Actions\ParticipateQuizAction;
use App\Modules\TravelAgency\Domain\Enums\QuizStatus;
use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
<<<<<<< HEAD
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\ParticipateTravelQuizRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelQuizQuestionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelQuizRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelQuizQuestionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\UpdateTravelQuizRequest;
=======
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\ParticipateTravelQuizRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelQuizQuestionRequest;
use App\Modules\TravelAgency\Interfaces\Api\V1\Requests\StoreTravelQuizRequest;
>>>>>>> origin/feat/travel-101-202-foundations
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TRAVEL-904 (#6107) — Quiz & jeu-concours.
 *
 * Le participant ne reçoit JAMAIS les réponses correctes : `show` expose
 * les questions sans `correct_option_index` ; la notation est serveur
 * (`ParticipateQuizAction`). Participation unique par (quiz, email) → 409.
 */
class TravelQuizController extends Controller
{
    public function __construct(private readonly ParticipateQuizAction $participate) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewAny', TravelQuiz::class)) {
            abort(403);
        }

        $quizzes = TravelQuiz::query()
            ->where('company_id', $actor->company_id)
            ->when($request->query('status'), fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $quizzes->map(fn (TravelQuiz $q) => [
            'id' => $q->id,
            'title' => $q->title,
            'status' => $q->status->value,
            'starts_at' => $q->starts_at?->toIso8601String(),
            'ends_at' => $q->ends_at?->toIso8601String(),
        ])]);
    }

    /**
     * Détail pour participation : questions SANS réponse correcte.
     */
    public function show(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('view', $travelQuiz)) {
            abort(404);
        }

        $questions = $travelQuiz->questions()->orderBy('position')->get()->map(fn ($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'options' => $q->options,
            'points' => $q->points,
        ]);

        return response()->json([
            'data' => [
                'id' => $travelQuiz->id,
                'title' => $travelQuiz->title,
                'description' => $travelQuiz->description_redacted,
                'status' => $travelQuiz->status->value,
                'questions' => $questions,
            ],
        ]);
    }

    public function store(StoreTravelQuizRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('create', TravelQuiz::class)) {
            abort(403);
        }

        $quiz = TravelQuiz::query()->create([
            'company_id' => $actor->company_id,
            'title' => trim((string) $request->validated('title')),
            'description_redacted' => $request->validated('description'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'max_participations_per_contact' => (int) ($request->validated('max_participations_per_contact') ?? 1),
            'status' => $request->validated('status') ?? QuizStatus::DRAFT->value,
        ]);

        return response()->json(['data' => ['id' => $quiz->id, 'status' => $quiz->status->value]], 201);
    }

    public function storeQuestion(StoreTravelQuizQuestionRequest $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $position = (int) ($request->validated('position') ?? $travelQuiz->questions()->max('position') + 1);

        $question = $travelQuiz->questions()->create([
            'company_id' => $actor->company_id,
            'question' => trim((string) $request->validated('question')),
            'options' => $request->validated('options'),
            'correct_option_index' => (int) $request->validated('correct_option_index'),
            'points' => (int) ($request->validated('points') ?? 1),
            'position' => $position,
        ]);

        return response()->json(['data' => ['id' => $question->id]], 201);
    }

<<<<<<< HEAD
    /**
     * TRAVEL-914 (#6422) — Liste admin des questions d'un quiz AVEC la
     * bonne réponse (réservée aux rôles gestion via TravelQuizPolicy::update).
     * L'endpoint public `show` reste la seule surface côté participants
     * (sans correct_option_index).
     */
    public function questionsIndex(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $questions = $travelQuiz->questions()->orderBy('position')->get()->map(fn ($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'options' => $q->options,
            'correct_option_index' => $q->correct_option_index,
            'points' => $q->points,
            'position' => $q->position,
        ]);

        return response()->json(['data' => $questions]);
    }

    /**
     * TRAVEL-914 (#6422) — Mise à jour d'un quiz (gestion admin).
     * La bonne réponse n'est jamais exposée en lecture ; elle n'est
     * acceptée qu'en écriture (requests dédiées).
     */
    public function update(UpdateTravelQuizRequest $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        $travelQuiz->forceFill([
            'title' => trim((string) $request->validated('title')),
            'description_redacted' => $request->validated('description'),
            'starts_at' => $request->validated('starts_at'),
            'ends_at' => $request->validated('ends_at'),
            'max_participations_per_contact' => (int) ($request->validated('max_participations_per_contact') ?? $travelQuiz->max_participations_per_contact),
            'status' => $request->validated('status') ?? $travelQuiz->status->value,
        ])->save();

        return response()->json(['data' => ['id' => $travelQuiz->id, 'status' => $travelQuiz->status->value]]);
    }

    /**
     * TRAVEL-914 (#6422) — Mise à jour d'une question de quiz (gestion admin).
     */
    public function updateQuestion(UpdateTravelQuizQuestionRequest $request, TravelQuiz $travelQuiz, TravelQuizQuestion $travelQuizQuestion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        if ($travelQuizQuestion->quiz_id !== $travelQuiz->id || $travelQuizQuestion->company_id !== $actor->company_id) {
            abort(404);
        }

        $travelQuizQuestion->forceFill([
            'question' => trim((string) $request->validated('question')),
            'options' => $request->validated('options'),
            'correct_option_index' => (int) $request->validated('correct_option_index'),
            'points' => (int) ($request->validated('points') ?? $travelQuizQuestion->points),
            'position' => (int) ($request->validated('position') ?? $travelQuizQuestion->position),
        ])->save();

        return response()->json(['data' => ['id' => $travelQuizQuestion->id]]);
    }

    /**
     * TRAVEL-914 (#6422) — Suppression d'une question de quiz (gestion admin).
     */
    public function destroyQuestion(Request $request, TravelQuiz $travelQuiz, TravelQuizQuestion $travelQuizQuestion): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('update', $travelQuiz)) {
            abort(403);
        }

        if ($travelQuizQuestion->quiz_id !== $travelQuiz->id || $travelQuizQuestion->company_id !== $actor->company_id) {
            abort(404);
        }

        $travelQuizQuestion->delete();

        return response()->json(null, 204);
    }

=======
>>>>>>> origin/feat/travel-101-202-foundations
    public function participate(ParticipateTravelQuizRequest $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('participate', $travelQuiz)) {
            abort(404);
        }

        $result = $this->participate->execute(
            $travelQuiz,
            (string) $request->validated('participant_email'),
            $request->validated('participant_name'),
            $request->validated('answers'),
        );

        return response()->json([
            'data' => [
                'participation_id' => $result['participation']->id,
                'score' => $result['score'],
                'bonus' => $result['bonus'],
            ],
        ], 201);
    }

    public function results(Request $request, TravelQuiz $travelQuiz): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($actor->cannot('viewResults', $travelQuiz)) {
            abort(403);
        }

        $participations = $travelQuiz->participations()
            ->orderByDesc('score')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'participant_email' => $p->participant_email,
                'participant_name' => $p->participant_name,
                'score' => $p->score,
                'bonus' => $p->bonus,
                'submitted_at' => $p->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $participations]);
    }
}
