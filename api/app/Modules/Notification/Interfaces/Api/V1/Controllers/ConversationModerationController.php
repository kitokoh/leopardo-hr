<?php

declare(strict_types=1);

namespace App\Modules\Notification\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Domain\Models\ConversationBlock;
use App\Modules\Notification\Domain\Models\ConversationReport;
use App\Modules\Notification\Infrastructure\Services\ConversationModerationService;
use App\Modules\Notification\Infrastructure\Services\ConversationService;
use App\Modules\Notification\Interfaces\Api\V1\Requests\StoreConversationReportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * #8750 [BOS-CHAT] — modération de base du chat horizontal.
 *
 * Deux familles d'endpoints, deux niveaux de droit distincts :
 *
 *  - **utilisateur** (`blocks`, `reports` émis par soi) : tout employé
 *    authentifié agit sur SES propres blocages et SES propres signalements.
 *    Aucun identifiant dans l'URL ne permet d'atteindre ceux d'un autre :
 *    l'acteur est toujours `$request->user()`.
 *  - **modération** (`moderation/reports`) : réservée aux `manager` du tenant
 *    (`Employee::isManager()`, déjà existant — aucun nouveau rôle).
 *
 * ⚠️ Le modérateur ne gagne AUCUN accès aux conversations : aucun de ces
 * endpoints ne rend un message d'un fil dont il n'est pas participant. Il voit
 * le motif, le statut et les identifiants — jamais le contenu.
 */
class ConversationModerationController extends Controller
{
    public function __construct(
        private readonly ConversationModerationService $moderationService,
        private readonly ConversationService $conversationService,
    ) {}

    /**
     * GET /conversations/blocks — employés bloqués par l'acteur.
     */
    public function indexBlocks(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 50)));
        $blocks = $this->moderationService->blocksFor($actor, $perPage);

        return response()->json([
            'data' => $blocks->getCollection()
                ->map(static fn (ConversationBlock $block): array => [
                    'employee_id' => $block->blocked_id,
                    'name' => trim((string) (($block->blocked->first_name ?? '').' '.($block->blocked->last_name ?? ''))),
                    'matricule' => $block->blocked->matricule ?? null,
                    'reason' => $block->reason,
                    'created_at' => $block->created_at->toIso8601String(),
                ])
                ->values(),
            'meta' => [
                'current_page' => $blocks->currentPage(),
                'last_page' => $blocks->lastPage(),
                'total' => $blocks->total(),
            ],
        ]);
    }

    /**
     * POST /conversations/blocks — bloque un employé (idempotent).
     */
    public function storeBlock(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $block = $this->moderationService->block(
            $actor,
            (int) $validated['employee_id'],
            isset($validated['reason']) ? (string) $validated['reason'] : null,
        );

        return response()->json([
            'data' => [
                'employee_id' => $block->blocked_id,
                'reason' => $block->reason,
                'created_at' => $block->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * DELETE /conversations/blocks/{employee} — débloque (silencieux si absent).
     */
    public function destroyBlock(Request $request, int $employee): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->moderationService->unblock($actor, $employee);

        return response()->json([], 204);
    }

    /**
     * POST /conversations/{thread}/reports — signale un message du fil.
     *
     * L'appelant doit être participant du fil (`findForActor` → 404 sinon),
     * donc un signalement ne peut pas servir à sonder l'existence d'un fil ni
     * à atteindre un message d'un autre tenant.
     */
    public function storeMessageReport(StoreConversationReportRequest $request, int $thread): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $conversationThread = $this->conversationService->findForActor($thread, $actor);

        $validated = $request->validated();

        $report = isset($validated['message_id'])
            ? $this->moderationService->reportMessage(
                $conversationThread,
                $actor,
                (int) $validated['message_id'],
                (string) $validated['reason'],
                isset($validated['details']) ? (string) $validated['details'] : null,
            )
            : $this->moderationService->reportEmployee(
                $actor,
                (int) $validated['employee_id'],
                (string) $validated['reason'],
                isset($validated['details']) ? (string) $validated['details'] : null,
            );

        return response()->json(['data' => $this->serializeReport($report)], 201);
    }

    /**
     * POST /conversations/reports — signale un compte (sans message).
     */
    public function storeAccountReport(StoreConversationReportRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validated();

        if (! isset($validated['employee_id'])) {
            // Signaler un message exige un fil : cet endpoint n'en porte pas.
            abort(422, __('conversations.report_requires_conversation'));
        }

        $report = $this->moderationService->reportEmployee(
            $actor,
            (int) $validated['employee_id'],
            (string) $validated['reason'],
            isset($validated['details']) ? (string) $validated['details'] : null,
        );

        return response()->json(['data' => $this->serializeReport($report)], 201);
    }

    /**
     * GET /conversations/reports — signalements émis par l'acteur.
     */
    public function indexOwnReports(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));
        $reports = $this->moderationService->reportsFiledBy($actor, $perPage);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (ConversationReport $report): array => $this->serializeReport($report))->values(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * GET /conversations/moderation/reports — file de modération (managers).
     */
    public function indexModerationQueue(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $status = $request->query('status');
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        $reports = $this->moderationService->reportsFor(
            $actor,
            is_string($status) && $status !== '' ? $status : null,
            $perPage,
        );

        return response()->json([
            'data' => $reports->getCollection()->map(function (ConversationReport $report): array {
                $payload = $this->serializeReport($report);
                // Identité du signalant : utile au modérateur, jamais rendue
                // publique côté participant.
                $payload['reporter'] = $report->reporter !== null ? [
                    'id' => $report->reporter->id,
                    'name' => trim((string) (($report->reporter->first_name ?? '').' '.($report->reporter->last_name ?? ''))),
                ] : null;
                $payload['reviewed_by'] = $report->reviewedBy !== null ? [
                    'id' => $report->reviewedBy->id,
                    'name' => trim((string) (($report->reviewedBy->first_name ?? '').' '.($report->reviewedBy->last_name ?? ''))),
                ] : null;

                return $payload;
            })->values(),
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    /**
     * POST /conversations/moderation/reports/{report}/resolve — clôture (managers).
     */
    public function resolveReport(Request $request, int $report): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:reviewed,dismissed'],
            'resolution' => ['nullable', 'string', 'max:500'],
        ]);

        $resolved = $this->moderationService->resolve(
            $actor,
            $report,
            (string) $validated['status'],
            isset($validated['resolution']) ? (string) $validated['resolution'] : null,
        );

        return response()->json(['data' => $this->serializeReport($resolved)]);
    }

    /**
     * Sérialisation commune. Aucun corps de message n'y figure : la modération
     * ne divulgue pas le contenu d'une conversation privée.
     *
     * @return array<string, mixed>
     */
    private function serializeReport(ConversationReport $report): array
    {
        return [
            'id' => $report->id,
            'status' => $report->status,
            'reason' => $report->reason,
            'details' => $report->details,
            'conversation_thread_id' => $report->conversation_thread_id,
            'conversation_message_id' => $report->conversation_message_id,
            'reported_employee_id' => $report->reported_employee_id,
            'created_at' => $report->created_at?->toIso8601String(),
            'reviewed_at' => $report->reviewed_at?->toIso8601String(),
            'resolution' => $report->resolution,
        ];
    }
}
