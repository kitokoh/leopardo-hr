<?php

declare(strict_types=1);

namespace App\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\AI\Exceptions\ToolPermissionDeniedException;
use App\AI\Support\ConfirmationSummary;
use App\AI\Support\ConfirmedWriteExecutor;
use App\AI\Support\ReadToolRegistry;
use App\AI\Support\ToolSchemaGuard;

/**
 * Point d'entree de l'execution des tool calls de l'assistant.
 *
 * ## Ce que ce fichier est devenu (#9887)
 *
 * Il faisait 1 375 lignes et 37 methodes : execution des tool calls, garde de
 * schema, ecritures confirmees, resume de confirmation, et les 19 handlers de
 * lecture RH/Paie/Communication. Cinq responsabilites dans un fichier, sur le
 * chemin le plus sensible du module IA.
 *
 * C'est desormais une **façade** : elle garde exactement la meme API publique
 * (`executeToolCalls`, `executeConfirmedWrite`, `supportedReadToolNames`,
 * `supportedReadTools`) et delegue a des collaborateurs a contrat explicite :
 *
 * | Collaborateur | Responsabilite |
 * |---|---|
 * | `ToolSchemaGuard` | validation entree/sortie + audit (BOS-034) |
 * | `ReadToolRegistry` | map nom d'outil -> handler, source unique (#5625) |
 * | `HrReadHandlers` / `PayrollReadHandlers` / `CommunicationReadHandlers` | les handlers |
 * | `ConfirmedWriteExecutor` | ecritures confirmees + idempotence (BOS-032) |
 * | `ConfirmationSummary` | texte lu par l'utilisateur avant de confirmer |
 *
 * ## Ce qui n'a PAS change, et qui devait ne pas changer
 *
 * L'ordre des verrous est identique : permission (fail-closed, avant tout
 * effet de bord) -> schema d'entree -> confirmation -> dispatch -> schema de
 * sortie. Une ecriture ne cree jamais de pending action si la permission est
 * refusee. Le role est resolu UNE fois par boucle de tool calls.
 *
 * `dispatchToolAction` reste une methode privee de cette classe : un test
 * l'invoque par reflexion pour verifier le message « not yet implemented ».
 * La deplacer casserait ce contrat sans rien apporter.
 */
class IntentEngine
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly WriteToolPolicy $writeToolPolicy,
        private readonly PendingActionStore $pendingActionStore,
        // BC-23-D05 (issue #6237) : matrice de permissions par outil AI.
        private readonly ToolPermissionPolicy $toolPermissionPolicy,
        // BOS-034 (#8223) : validation runtime des schemas d'outils.
        private readonly ToolSchemaGuard $schemaGuard,
        private readonly ReadToolRegistry $readTools,
        private readonly ConfirmedWriteExecutor $confirmedWrites,
        private readonly ConfirmationSummary $confirmationSummary,
    ) {}

    /**
     * Issue #5625 : liste statique des outils read qui ont un handler PHP.
     * Utilisee par l'Orchestrator pour filtrer le registre DB avant d'exposer
     * les outils au LLM — un outil enregistre en DB mais absent ici ne sera
     * JAMAIS propose a l'utilisateur.
     *
     * @return list<string>
     */
    public static function supportedReadToolNames(): array
    {
        return [
            'get_employees',
            'get_employee_details',
            'get_departments',
            'get_headcount',
            'search_employees',
            'get_attendance_today',
            'get_attendance_anomalies',
            'get_monthly_report',
            'get_absences',
            'get_daily_summary',
            'get_notifications',
            'get_leave_balances',
            'get_payroll_summary',
            // B1 (#6854) — outils lecture BC-04 HR (contrat A3 #6850).
            'team_overview',
            'team_absences_recent',
            'employee_leave_balance',
            // B2 (#6855) — outil lecture BC-07 PAYROLL (contrat A3 #6850).
            'payroll_current_status',
            // R3/R5 Communication (#7688/#7690, contrat A3 #6850) — outils
            // lecture BC-29 : classification IA d'un email synchronise et
            // brouillon de reponse assistee (file Pending, jamais d'envoi).
            'email_classify',
            'email_reply_draft',
        ];
    }

    /**
     * @return array<int, ToolResult>
     */
    public function executeToolCalls(AIResponse $response, string $companyId, int $userId, ?int $conversationId = null): array
    {
        // BC-23-D05 : le role est resolu une seule fois pour toute la boucle
        // de tool calls (evite N requetes Employee).
        $role = $this->toolPermissionPolicy->resolveRole($userId, $companyId);
        $results = [];

        foreach ($response->toolCalls as $toolCall) {
            $results[] = $this->executeSingleTool($toolCall, $companyId, $userId, $role, $conversationId);
        }

        return $results;
    }

    private function executeSingleTool(ToolCall $toolCall, string $companyId, int $userId, string $role, ?int $conversationId = null): ToolResult
    {
        // BC-23-D05 : fail-closed — l'appel d'un outil hors matrice (role ou
        // permission insuffisante) est refuse AVANT tout effet de bord
        // (y compris la creation d'une pending action pour les write-tools).
        try {
            $this->toolPermissionPolicy->assertCanUse($toolCall->name, $role);
        } catch (ToolPermissionDeniedException $exception) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode([
                    'error' => $exception->errorCode(),
                    'message' => 'AI tool permission denied',
                ]) ?: '{}',
                success: false,
            );
        }

        $tool = $this->toolRegistry->findTool($toolCall->name);

        // BOS-034 (#8223) — validation des ARGUMENTS produits par le LLM
        // contre le schema declare, AVANT toute proposition ou execution
        // (un argument hostile ou malforme ne doit jamais atteindre un
        // handler). Mode `warn` : audite et laisse passer (1 semaine) ; mode
        // `strict` : refus fail-closed.
        if ($tool !== null) {
            $inputViolation = $this->schemaGuard->gate(
                'input',
                $toolCall,
                $this->schemaGuard->inputSchemaOf($tool),
                $toolCall->arguments,
                $companyId,
                $userId,
            );
            if ($inputViolation !== null) {
                return $inputViolation;
            }
        }

        if ($this->writeToolPolicy->requiresConfirmation($toolCall->name)) {
            return $this->pendingConfirmationResult($toolCall, $companyId, $userId, $conversationId);
        }

        if ($tool === null) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode(['error' => "Unknown tool: {$toolCall->name}"]) ?: '{}',
                success: false,
            );
        }

        try {
            $result = $this->dispatchToolAction($toolCall->name, $toolCall->arguments, $companyId, $userId);

            // BOS-034 (#8223) — validation de la SORTIE contre l'outputSchema
            // declare (fail-closed en mode strict + audit) : une sortie non
            // conforme ne repart pas au LLM comme si de rien n'etait.
            $outputViolation = $this->schemaGuard->gate(
                'output',
                $toolCall,
                $this->schemaGuard->outputSchemaOf($tool),
                $result,
                $companyId,
                $userId,
            );
            if ($outputViolation !== null) {
                return $outputViolation;
            }

            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                // #9077 — `JSON_PRESERVE_ZERO_FRACTION` : le contrat A3 (#6850)
                // declare `balance`/`used`/`pending` comme des decimaux, et les
                // modeles les castent en `float`. Sans ce drapeau, un solde de
                // 20.0 etait serialise « 20 » et le type annonce au modele
                // devenait un entier — un consommateur qui compare au contrat
                // voyait un changement de type. Le drapeau ne change que la
                // representation textuelle d'un flottant entier, jamais sa
                // valeur JSON.
                content: json_encode($result, JSON_PRESERVE_ZERO_FRACTION) ?: '{}',
                success: true,
            );
        } catch (\Throwable $e) {
            return new ToolResult(
                toolCallId: $toolCall->id,
                name: $toolCall->name,
                content: json_encode(['error' => $e->getMessage()]) ?: '{}',
                success: false,
            );
        }
    }

    /**
     * Execute un write-tool APRES confirmation humaine.
     *
     * BOS-032 (#8222) — idempotence metier : avant toute execution, la cle
     * d'intention (conversation + empreinte des arguments) est consultee ;
     * une intention deja executee retourne son resultat initial avec le
     * marqueur `idempotent_replay` au lieu de produire un second effet.
     * Apres une execution reussie, le resultat est persiste (TTL
     * `ai.write_idempotency_ttl_hours`) pour servir les rejeux — y compris le
     * retry reseau sur le MEME pending_action_id (via le controleur, qui
     * consulte findByPendingActionId avant meme le pull one-shot).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function executeConfirmedWrite(string $toolName, array $arguments, string $companyId, int $userId, ?string $pendingActionId = null, ?int $conversationId = null): array
    {
        return $this->confirmedWrites->execute($toolName, $arguments, $companyId, $userId, $pendingActionId, $conversationId);
    }

    private function pendingConfirmationResult(ToolCall $toolCall, string $companyId, int $userId, ?int $conversationId = null): ToolResult
    {
        $pendingId = $this->pendingActionStore->store(
            $companyId,
            $userId,
            $toolCall->name,
            $toolCall->arguments,
            $conversationId,
        );

        $payload = [
            'status' => 'confirmation_required',
            'pending_action_id' => $pendingId,
            'tool' => $toolCall->name,
            'summary' => $this->confirmationSummary->forTool($toolCall->name, $toolCall->arguments),
            'arguments' => $toolCall->arguments,
        ];

        return new ToolResult(
            toolCallId: $toolCall->id,
            name: $toolCall->name,
            content: json_encode($payload) ?: '{}',
            success: true,
        );
    }

    /**
     * Noms des read-tools effectivement dispatchables (issue #5625).
     *
     * @return list<string>
     */
    public function supportedReadTools(): array
    {
        return $this->readTools->names();
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function dispatchToolAction(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        $handler = $this->readTools->handlers($companyId, $userId)[$toolName] ?? null;
        if ($handler === null) {
            return ['message' => "Tool '{$toolName}' is registered but not yet implemented."];
        }

        return $handler($arguments);
    }
}
