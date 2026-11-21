<?php

declare(strict_types=1);

namespace App\AI\Interfaces\Api\V1\Controllers;

use App\AI\AIAuditLogger;
use App\AI\PendingActionStore;
use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolDefinitionRegistry;
use App\AI\Support\AIToolSensitivity;
use App\AI\ToolPermissionPolicy;
use App\AI\WriteToolPolicy;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proposition d'action sensible depuis un client MCP externe (issue #9753).
 *
 * Le serveur MCP (`dev-hub/mcp-server`) n'a — et ne doit avoir — aucun accès à
 * la base : c'est ce qui garantit que Laravel reste le SEUL point
 * d'autorisation. Il ne peut donc pas écrire lui-même dans `ai_pending_actions`.
 *
 * Cet endpoint est le pont manquant : il reçoit `{tool, arguments}`, applique
 * les mêmes contrôles que le chat (rôle, matrice de permissions, sensibilité,
 * existence d'un handler de confirmation) puis délègue à `PendingActionStore`.
 * Aucune logique de stockage n'est dupliquée.
 *
 * Ce qui est créé est une PROPOSITION, pas une exécution : l'effet métier
 * n'aura lieu qu'après `POST /api/v1/ai/actions/{id}/confirm` par un humain
 * (spec §3 « jamais de confiance au LLM »).
 */
class AIActionProposalController extends Controller
{
    public function __construct(
        private readonly PendingActionStore $pendingActionStore,
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
        private readonly WriteToolPolicy $writeToolPolicy,
        private readonly AIAuditLogger $auditLogger,
    ) {}

    public function propose(Request $request): JsonResponse
    {
        /** @var array{tool: string, arguments?: array<string, mixed>} $validated */
        $validated = $request->validate([
            'tool' => 'required|string|max:64',
            'arguments' => 'nullable|array',
        ]);

        $toolName = $validated['tool'];
        /** @var array<string, mixed> $arguments */
        $arguments = $validated['arguments'] ?? [];

        /** @var Employee $user */
        $user = $request->user();
        $companyId = (string) $user->company_id;
        $userId = (int) $user->id;

        // 1. L'outil doit exister dans le contrat A3. Un nom inconnu est refusé
        //    AVANT tout stockage : on ne crée pas de pending pour un outil
        //    fantôme (fail-closed).
        $definition = AIToolDefinitionRegistry::all()[$toolName] ?? null;
        if (! $definition instanceof AIToolDefinition) {
            return $this->refuse(
                'AI_TOOL_UNKNOWN',
                sprintf("Outil inconnu : '%s'.", $toolName),
                422,
            );
        }

        // 2. Proposer une LECTURE n'a aucun sens : la lecture s'exécute
        //    directement via `tools/call`. Seules les sensibilités write/send
        //    passent par une confirmation humaine.
        if ($definition->sensitivity === AIToolSensitivity::Read) {
            return $this->refuse(
                'AI_ACTION_NOT_SENSITIVE',
                sprintf("L'outil '%s' est en lecture : exécution directe, aucune confirmation requise.", $toolName),
                422,
            );
        }

        // 3. L'outil doit avoir un HANDLER de confirmation. Sans handler,
        //    `IntentEngine::executeConfirmedWrite()` refuserait à la
        //    confirmation : créer un pending serait une promesse fantôme
        //    (l'utilisateur confirmerait une action qui ne peut pas s'exécuter).
        if (! $this->writeToolPolicy->requiresConfirmation($toolName)) {
            return $this->refuse(
                'AI_ACTION_NOT_CONFIRMABLE',
                sprintf("L'outil '%s' n'a pas de handler de confirmation : proposition impossible.", $toolName),
                422,
            );
        }

        // 4. RBAC — même matrice que l'exécution (BC-23-D05). Refus AVANT tout
        //    stockage : aucun pending n'est créé pour un utilisateur non habilité.
        $role = $this->toolPermissionPolicy->resolveRole($userId, $companyId);
        if (! $this->toolPermissionPolicy->canUse($toolName, $role)) {
            return $this->refuse(
                'AI_TOOL_PERMISSION_DENIED',
                sprintf("Rôle '%s' non habilité pour l'outil '%s'.", $role, $toolName),
                403,
            );
        }

        // 5. Stockage : délégation intégrale au store existant (TTL, isolation
        //    tenant/utilisateur, backend cache ou database selon la config).
        $pendingActionId = $this->pendingActionStore->store(
            $companyId,
            $userId,
            $toolName,
            $arguments,
            null,
        );

        // 6. Audit : la chaîne proposition → confirmation reste traçable sous
        //    le même pending_action_id (même mécanique que le chat, A5 #6852).
        $this->auditLogger->logToolExecution(
            companyId: $companyId,
            userId: $userId,
            conversationId: null,
            pendingActionId: $pendingActionId,
            toolName: $toolName,
            toolInput: $arguments,
            stage: 'proposed',
            success: true,
            resultSummary: null,
        );

        return response()->json([
            'data' => [
                'status' => 'confirmation_required',
                'pending_action_id' => $pendingActionId,
                'tool' => $toolName,
                'sensitivity' => $definition->sensitivity->value,
                'arguments' => $arguments,
                'confirm_endpoint' => sprintf('/api/v1/ai/actions/%s/confirm', $pendingActionId),
                'reject_endpoint' => sprintf('/api/v1/ai/actions/%s/reject', $pendingActionId),
            ],
        ], 201);
    }

    private function refuse(string $code, string $detail, int $status): JsonResponse
    {
        return response()->json([
            'error' => $code,
            'message' => $code,
            'detail' => $detail,
        ], $status);
    }
}
