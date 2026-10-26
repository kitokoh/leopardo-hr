<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Modules\SearchIntelligence\Domain\Enums\AeoQuestionStatus;
use App\Modules\SearchIntelligence\Domain\Exceptions\ReviewRequiredException;
use App\Modules\SearchIntelligence\Domain\Models\AeoQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GSI-4 (#8515) — bibliothèque AEO questions-réponses.
 *
 * ```
 * GET    /aeo/questions                  — liste filtrable (locale, sector, status)
 * POST   /aeo/questions                  — création (brouillon)
 * PATCH  /aeo/questions/{id}             — édition du brouillon
 * POST   /aeo/questions/{id}/submit      — draft → in_review
 * POST   /aeo/questions/{id}/approve     — validation humaine : FIGE la réponse
 * POST   /aeo/questions/{id}/publish     — publie la réponse figée
 * POST   /aeo/questions/{id}/to-update   — retire du public sans détruire
 * ```
 *
 * Le point d'architecture important : `approve` copie le brouillon dans
 * `answer_approved`. Ce que publie `publish` est donc le texte RELU, figé au
 * moment de la validation. Si quelqu'un modifie le brouillon ensuite, la
 * version publiée ne bouge pas — il faut une nouvelle validation.
 */
final class SearchIntelAeoController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'string', 'max:40'],
            'status' => ['sometimes', Rule::in(array_map(
                static fn (AeoQuestionStatus $s): string => $s->value,
                AeoQuestionStatus::cases(),
            ))],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $query = AeoQuestion::query();

        foreach (['locale', 'sector', 'status'] as $filter) {
            if (isset($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        $items = $query->orderByDesc('id')->limit((int) ($validated['limit'] ?? 100))->get();

        return new JsonResponse([
            'data' => $items->map(fn (AeoQuestion $q): array => $this->present($q))->values(),
            'meta' => ['total' => $items->count()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'answer_draft' => ['sometimes', 'nullable', 'string'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sources' => ['sometimes', 'nullable', 'array'],
        ]);

        $question = new AeoQuestion;
        $question->fill([
            'question' => $validated['question'],
            'answer_draft' => $validated['answer_draft'] ?? null,
            'locale' => $validated['locale'] ?? 'fr',
            'sector' => $validated['sector'] ?? null,
            'sources' => $validated['sources'] ?? null,
        ]);
        // Statut posé EXPLICITEMENT : il n'est pas mass-assignable (une question
        // ne naît jamais « publiée »), et le défaut SQL n'est pas hydraté sur
        // l'instance après `save()` — sans cette ligne la réponse sérialiserait
        // un statut nul.
        $question->status = AeoQuestionStatus::Draft;
        $question->save();

        return new JsonResponse(['data' => $this->present($question)], 201);
    }

    public function update(Request $request, AeoQuestion $question): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['sometimes', 'string', 'max:1000'],
            'answer_draft' => ['sometimes', 'nullable', 'string'],
            'locale' => ['sometimes', 'string', 'max:10'],
            'sector' => ['sometimes', 'nullable', 'string', 'max:40'],
            'sources' => ['sometimes', 'nullable', 'array'],
        ]);

        // Seul un brouillon s'édite librement. Après validation, modifier le
        // texte imposerait de repasser par la revue — c'est ce que fait
        // `to_update`, exprès.
        if ($question->status !== AeoQuestionStatus::Draft) {
            return new JsonResponse([
                'error' => [
                    'code' => 'question_not_editable',
                    'message' => 'Seul un brouillon est éditable ; repassez la question en brouillon pour la modifier.',
                    'current_status' => $question->status->value,
                ],
            ], 422);
        }

        $question->fill($validated)->save();

        return new JsonResponse(['data' => $this->present($question)]);
    }

    public function submit(AeoQuestion $question): JsonResponse
    {
        return $this->act($question, static fn (AeoQuestion $q): null => $q->submitForReview(), 'in_review');
    }

    public function approve(Request $request, AeoQuestion $question): JsonResponse
    {
        $actor = $request->user('super_admin_api');
        $actorId = $actor instanceof SuperAdmin ? $actor->id : null;

        if ($actorId === null) {
            return new JsonResponse([
                'error' => [
                    'code' => 'actor_required',
                    'message' => 'La validation exige un relecteur identifié.',
                ],
            ], 422);
        }

        return $this->act($question, static fn (AeoQuestion $q): null => $q->approve($actorId), 'approved');
    }

    public function publish(AeoQuestion $question): JsonResponse
    {
        return $this->act($question, static fn (AeoQuestion $q): null => $q->publish(), 'published');
    }

    public function toUpdate(AeoQuestion $question): JsonResponse
    {
        return $this->act($question, static fn (AeoQuestion $q): null => $q->markToUpdate(), 'to_update');
    }

    /**
     * Exécute une transition, persiste, audite. Les trois transitions d'état
     * passent par ici : il n'existe pas de chemin qui contourne l'audit.
     *
     * @param  callable(AeoQuestion): null  $mutation
     */
    private function act(AeoQuestion $question, callable $mutation, string $targetStatus): JsonResponse
    {
        $from = $question->status->value;

        try {
            $mutation($question);
        } catch (ReviewRequiredException $exception) {
            return new JsonResponse([
                'error' => [
                    'code' => $exception->errorCode(),
                    'message' => $exception->getMessage(),
                    'current_status' => $from,
                ],
            ], 422);
        }

        $question->save();

        AuditLog::query()->create([
            'company_id' => null,
            'user_id' => null,
            'action' => 'search_intel.aeo_question.'.$targetStatus,
            'module' => 'search_intel',
            'auditable_type' => AeoQuestion::class,
            'auditable_id' => $question->id,
            'old_values' => ['status' => $from],
            'new_values' => ['status' => $question->status->value],
            'metadata' => null,
        ]);

        return new JsonResponse(['data' => $this->present($question)]);
    }

    /** @return array<string, mixed> */
    private function present(AeoQuestion $question): array
    {
        return [
            'id' => $question->id,
            'question' => $question->question,
            'answer_draft' => $question->answer_draft,
            // Le figé n'est exposé qu'une fois qu'il existe : avant validation
            // il n'y a rien de relu à montrer.
            'answer_approved' => $question->answer_approved,
            'locale' => $question->locale,
            'sector' => $question->sector,
            'status' => $question->status->value,
            'status_label' => $question->status->label(),
            'approved_by' => $question->approved_by,
            'approved_at' => $question->approved_at?->toIso8601String(),
            'published_at' => $question->published_at?->toIso8601String(),
            'sources' => $question->sources,
            'allowed_transitions' => array_map(
                static fn (AeoQuestionStatus $s): string => $s->value,
                $question->status->allowedTransitions(),
            ),
        ];
    }
}
