<?php

declare(strict_types=1);

namespace App\AI\Support;

use App\AI\Exceptions\ToolPermissionDeniedException;
use App\AI\ToolPermissionPolicy;
use App\AI\ToolRegistry;
use App\AI\WriteActionRunner;
use App\AI\WriteIdempotencyStore;
use App\AI\WriteToolPolicy;

/**
 * Execution d'un write-tool APRES confirmation humaine — extrait de
 * `IntentEngine` par #9887.
 *
 * ## Les trois verrous, dans cet ordre, et pourquoi l'ordre compte
 *
 *  1. **Permission re-verifiee** — le role du demandeur a pu changer entre le
 *     chat et le clic de confirmation. Verifier une seule fois, a la
 *     proposition, laisserait un employe retrograde confirmer une action qu'il
 *     n'a plus le droit de faire.
 *  2. **Schema d'entree revalide** — un pending stocke hors boucle chat n'est
 *     jamais passe par la garde d'`executeSingleTool`. En mode strict, le refus
 *     intervient AVANT tout effet et AVANT toute entree d'idempotence : sinon
 *     un rejeu retrouverait une entree pour une action qui n'a jamais eu lieu.
 *  3. **Idempotence metier** (BOS-032, #8222) — la cle d'intention
 *     (conversation + empreinte des arguments) est consultee avant execution ;
 *     une intention deja executee renvoie son resultat initial avec
 *     `idempotent_replay` au lieu de produire un second effet.
 *
 * ## Ce que la sortie validee ne doit PAS annuler
 *
 * En mode strict, une sortie non conforme est refusee cote client — mais
 * l'effet metier a EU LIEU et l'entree d'idempotence (le resultat reel) reste
 * persistee. Rejouer l'action ne doit pas la re-executer : c'est le resultat
 * reel qui est rejoue, pas l'intention.
 */
final class ConfirmedWriteExecutor
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly WriteToolPolicy $writeToolPolicy,
        private readonly WriteActionRunner $writeActionRunner,
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
        private readonly WriteIdempotencyStore $writeIdempotencyStore,
        private readonly ToolSchemaGuard $schemaGuard,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $toolName, array $arguments, string $companyId, int $userId, ?string $pendingActionId = null, ?int $conversationId = null): array
    {
        if (! $this->writeToolPolicy->requiresConfirmation($toolName)) {
            return ['error' => "Tool '{$toolName}' does not require confirmation."];
        }

        // BC-23-D05 : defense en profondeur — le role est re-verifie a la
        // confirmation (le role du demandeur peut avoir change entre le chat
        // et le clic de confirmation).
        $role = $this->toolPermissionPolicy->resolveRole($userId, $companyId);
        if (! $this->toolPermissionPolicy->canUse($toolName, $role)) {
            return [
                'error' => 'AI_TOOL_PERMISSION_DENIED',
                'code' => ToolPermissionDeniedException::ERROR_CODE,
            ];
        }

        // BOS-034 (#8223) — defense en profondeur a l'EXECUTION confirmee
        // (le dispatch reel d'un write-tool) : les arguments sont revalides
        // contre le schema declare — le role/mode a pu changer depuis la
        // proposition, et un pending stocke hors boucle chat n'est pas passe
        // par la garde d'executeSingleTool. Mode strict : refus AVANT tout
        // effet et avant toute entree d'idempotence.
        $tool = $this->toolRegistry->findTool($toolName);
        $inputViolations = $tool !== null
            ? $this->schemaGuard->validateAndAudit('input', $toolName, $this->schemaGuard->inputSchemaOf($tool), $arguments, $companyId, $userId)
            : [];
        if ($inputViolations !== [] && $this->schemaGuard->mode() === 'strict') {
            return [
                'error' => 'AI_TOOL_INPUT_SCHEMA_VIOLATION',
                'message' => 'Tool input does not match the declared JSON schema',
                'violations' => $inputViolations,
            ];
        }

        $argumentsHash = $this->writeIdempotencyStore->argumentsHash($arguments);
        $idempotencyKey = $this->writeIdempotencyStore->makeKey($companyId, $conversationId, $pendingActionId, $toolName, $argumentsHash);

        $replay = $this->writeIdempotencyStore->find($companyId, $idempotencyKey);
        if ($replay !== null) {
            return $replay['result'] + [
                'idempotent_replay' => true,
                'idempotency_key' => $idempotencyKey,
            ];
        }

        $result = $this->writeActionRunner->run($toolName, $arguments, $companyId, $userId);

        // Seuls les resultats SANS erreur sont persistes : une erreur n'a
        // produit aucun effet metier, un retry legitime doit pouvoir retenter.
        if (($result['error'] ?? null) === null) {
            $this->writeIdempotencyStore->store(
                $companyId,
                $idempotencyKey,
                $toolName,
                $argumentsHash,
                $pendingActionId,
                $conversationId,
                $result,
            );

            // BOS-034 (#8223) — sortie validee contre l'outputSchema declare.
            // L'effet metier A EU LIEU : l'entree d'idempotence (resultat
            // reel) reste persistee ci-dessus — en mode strict seule la
            // REPONSE est refusee (fail-closed cote client), jamais l'effet
            // rejoue (critere « violation → refus + audit »).
            $outputViolations = $tool !== null
                ? $this->schemaGuard->validateAndAudit('output', $toolName, $this->schemaGuard->outputSchemaOf($tool), $result, $companyId, $userId)
                : [];
            if ($outputViolations !== [] && $this->schemaGuard->mode() === 'strict') {
                return [
                    'error' => 'AI_TOOL_OUTPUT_SCHEMA_VIOLATION',
                    'message' => 'Tool output does not match the declared JSON schema',
                    'violations' => $outputViolations,
                ];
            }
        }

        return $result;
    }
}
