<?php

declare(strict_types=1);

namespace App\AI\Interfaces\Api\V1\Controllers;

use App\AI\AIAuditLogger;
use App\AI\DTOs\AIRequest;
use App\AI\IntentEngine;
use App\AI\Orchestrator;
use App\AI\PendingActionStore;
use App\AI\ToolPermissionPolicy;
use App\AI\ToolRegistry;
use App\AI\WriteIdempotencyStore;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIGatewayController extends Controller
{
    public function __construct(
        private readonly Orchestrator $orchestrator,
        private readonly IntentEngine $intentEngine,
        private readonly PendingActionStore $pendingActionStore,
        private readonly AIAuditLogger $auditLogger,
        // BOS-032 (#8222) — rejeu idempotent des confirmations (retry réseau).
        private readonly WriteIdempotencyStore $writeIdempotencyStore,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        /** @var array{message: string, conversation_id?: int} $validated */
        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'conversation_id' => 'nullable|integer',
        ]);

        /** @var Employee $user */
        $user = $request->user();

        $aiRequest = new AIRequest(
            message: $validated['message'],
            userId: (int) $user->id,
            companyId: (string) $user->company_id,
            conversationId: isset($validated['conversation_id']) ? (int) $validated['conversation_id'] : null,
        );

        $result = $this->orchestrator->handle($aiRequest);

        return response()->json(['data' => $result]);
    }

    public function history(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $conversations = DB::table('ai_conversations')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->select(['id', 'title', 'token_count', 'created_at', 'updated_at'])
            ->orderByDesc('updated_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $conversations->items(),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
                'per_page' => $conversations->perPage(),
                'total' => $conversations->total(),
            ],
        ]);
    }

    public function deleteConversation(Request $request, int $conversationId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $deleted = DB::table('ai_conversations')
            ->where('id', $conversationId)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->delete();

        if ($deleted === 0) {
            abort(404);
        }

        return response()->json(['message' => 'Conversation deleted.']);
    }

    public function confirmAction(Request $request, string $pendingActionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // BOS-032 (#8222) — retry réseau sur le MÊME pending après
        // consommation : le résultat initial est servi depuis le store
        // d'idempotence (marqueur idempotent_replay) au lieu d'un 404, sans
        // jamais ré-exécuter l'effet métier.
        $replayed = $this->writeIdempotencyStore->findByPendingActionId(
            (string) $user->company_id,
            $pendingActionId,
        );

        if ($replayed !== null) {
            $this->auditLogger->logToolExecution(
                companyId: (string) $user->company_id,
                userId: (int) $user->id,
                conversationId: $replayed['conversation_id'],
                pendingActionId: $pendingActionId,
                toolName: $replayed['tool'],
                toolInput: [],
                stage: 'idempotent_replay',
                success: true,
                resultSummary: (json_encode($replayed['result'], JSON_UNESCAPED_UNICODE) ?: null),
            );

            return response()->json([
                'data' => [
                    'status' => 'executed',
                    'tool' => $replayed['tool'],
                    'result' => $replayed['result'] + ['idempotent_replay' => true],
                ],
            ]);
        }

        $pending = $this->pendingActionStore->pull(
            $pendingActionId,
            (string) $user->company_id,
            (int) $user->id,
        );

        if ($pending === null) {
            abort(404, 'PENDING_ACTION_NOT_FOUND');
        }

        $result = $this->intentEngine->executeConfirmedWrite(
            $pending['tool'],
            $pending['arguments'],
            (string) $user->company_id,
            (int) $user->id,
            $pendingActionId,
            $pending['conversation_id'] ?? null,
        );

        // A5 (#6852) : l'exécution confirmée est journalisée — la chaîne se
        // relie à la proposition (même pending_action_id, posé au chat).
        $error = $result['error'] ?? null;
        $this->auditLogger->logToolExecution(
            companyId: (string) $user->company_id,
            userId: (int) $user->id,
            conversationId: null,
            pendingActionId: $pendingActionId,
            toolName: (string) $pending['tool'],
            toolInput: $pending['arguments'],
            stage: $error === null ? 'executed' : 'error',
            success: $error === null,
            resultSummary: $error === null ? (json_encode($result, JSON_UNESCAPED_UNICODE) ?: null) : null,
            error: is_string($error) ? $error : null,
        );

        if ($error !== null) {
            return response()->json(['error' => $error, 'data' => $result], 422);
        }

        return response()->json([
            'data' => [
                'status' => 'executed',
                'tool' => $pending['tool'],
                'result' => $result,
            ],
        ]);
    }

    public function rejectAction(Request $request, string $pendingActionId): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        $pending = $this->pendingActionStore->pull(
            $pendingActionId,
            (string) $user->company_id,
            (int) $user->id,
        );

        if ($pending === null) {
            abort(404, 'PENDING_ACTION_NOT_FOUND');
        }

        // A5 (#6852) : un refus humain est tracé (stage: rejected) — même
        // pending_action_id que la proposition issue du chat.
        $this->auditLogger->logToolExecution(
            companyId: (string) $user->company_id,
            userId: (int) $user->id,
            conversationId: null,
            pendingActionId: $pendingActionId,
            toolName: (string) $pending['tool'],
            toolInput: $pending['arguments'],
            stage: 'rejected',
            success: true,
            resultSummary: (string) __('errors.AI_ACTION_REJECTED'),
        );

        return response()->json([
            'data' => [
                'status' => 'rejected',
                'tool' => $pending['tool'],
            ],
        ]);
    }

    public function tools(Request $request): JsonResponse
    {
        /** @var Employee $user */
        $user = $request->user();

        // BC-23-D05 : n'exposer que les outils autorisés pour le rôle du
        // demandeur (miroir de l'exposition LLM dans l'Orchestrator) — un
        // employé ne voit pas les outils manager/admin.
        $policy = app(ToolPermissionPolicy::class);
        $registry = app(ToolRegistry::class);

        $role = $policy->resolveRole((int) $user->id, (string) $user->company_id);
        $tools = $registry->getToolsForRole($role, (string) $user->company_id);

        $payload = array_values(array_map(static fn (array $tool): array => [
            'id' => $tool['id'],
            'name' => $tool['name'],
            'description' => $tool['description'],
            'required_role' => $tool['required_role'],
            'module' => $tool['module'],
            'active' => $tool['active'],
        ], $tools));

        return response()->json(['data' => $payload]);
    }
}
