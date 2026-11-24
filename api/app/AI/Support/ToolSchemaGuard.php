<?php

declare(strict_types=1);

namespace App\AI\Support;

use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use Illuminate\Support\Facades\Log;

/**
 * Garde de validation par schema des tool calls — BOS-034 (#8223), extraite
 * de `IntentEngine` par #9887.
 *
 * ## Pourquoi cette classe existe separement
 *
 * La garde etait noyee dans un fichier de 1 375 lignes qui portait aussi les
 * 19 handlers de lecture et l'execution des ecritures confirmees. Or c'est le
 * point de securite le plus sensible du module IA : c'est lui qui decide si
 * les ARGUMENTS d'un LLM atteignent un handler, et si sa SORTIE repart vers le
 * modele. Isolee, elle est testable seule et relisible.
 *
 * ## Comportement inchange (contrainte de #9887)
 *
 *  - `warn` (defaut) : toute violation est AUDITEE et laissee passer ;
 *  - `strict` : refus fail-closed avec un `ToolResult` d'erreur.
 *
 * L'audit passe par la facade `Log` et non par un `LoggerInterface` injecte :
 * `ToolSchemaValidationTest` espionne la facade (`Log::spy()`) et verifie le
 * message `ai.tool_schema_violation`. Changer le canal casserait ces tests
 * sans rien apporter — la facade est ici le contrat observe.
 */
final class ToolSchemaGuard
{
    public function __construct(
        private readonly JsonSchemaValidator $jsonSchemaValidator,
    ) {}

    /**
     * Retourne null quand le flux peut continuer (aucune violation, schema
     * absent, ou mode `warn`) ; en mode `strict`, toute violation produit un
     * ToolResult de refus fail-closed.
     *
     * @param  array<string, mixed>  $schema
     */
    public function gate(string $phase, ToolCall $toolCall, array $schema, mixed $data, string $companyId, int $userId): ?ToolResult
    {
        $violations = $this->validateAndAudit($phase, $toolCall->name, $schema, $data, $companyId, $userId);
        if ($violations === [] || $this->mode() !== 'strict') {
            return null;
        }

        return new ToolResult(
            toolCallId: $toolCall->id,
            name: $toolCall->name,
            content: json_encode([
                'error' => $phase === 'input'
                    ? 'AI_TOOL_INPUT_SCHEMA_VIOLATION'
                    : 'AI_TOOL_OUTPUT_SCHEMA_VIOLATION',
                'message' => 'Tool '.$phase.' does not match the declared JSON schema',
                'violations' => $violations,
            ]) ?: '{}',
            success: false,
        );
    }

    /**
     * Valide `$data` contre `$schema` et audite TOUTE violation par log
     * structure `ai.tool_schema_violation` (chemins et regles attendues —
     * jamais les valeurs metier). Retourne la liste des violations (vide =
     * conforme ou schema absent) ; la decision warn/strict appartient a
     * l'appelant.
     *
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    public function validateAndAudit(string $phase, string $toolName, array $schema, mixed $data, string $companyId, int $userId): array
    {
        if ($schema === []) {
            return [];
        }

        $violations = $this->jsonSchemaValidator->validate($data, $schema);
        if ($violations === []) {
            return [];
        }

        Log::warning('ai.tool_schema_violation', [
            'company_id' => $companyId,
            'user_id' => $userId,
            'tool' => $toolName,
            'phase' => $phase,
            'mode' => $this->mode(),
            'violations' => $violations,
        ]);

        return $violations;
    }

    /**
     * Schema d'entree de l'outil : celui de l'AIToolDefinition declaree par le
     * BC proprietaire en priorite, sinon les `parameters` du registre DB (le
     * schema effectivement expose au LLM).
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    public function inputSchemaOf(array $tool): array
    {
        $declared = $tool['input_schema'] ?? null;
        if (is_array($declared) && $declared !== []) {
            return $declared;
        }

        $registered = $tool['parameters'] ?? null;

        return is_array($registered) ? $registered : [];
    }

    /**
     * Schema de sortie declare (uniquement les outils couverts par une
     * AIToolDefinition — les outils legacy sans definition restent hors
     * validation de sortie, documente dans la PR #8223).
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    public function outputSchemaOf(array $tool): array
    {
        $declared = $tool['output_schema'] ?? null;

        return is_array($declared) ? $declared : [];
    }

    /**
     * Mode de validation : `warn` (defaut — audit sans blocage, fenetre
     * d'observation d'une semaine) ou `strict` (fail-closed). Bascule par
     * config `ai.tool_schema_validation.mode` / `AI_TOOL_SCHEMA_VALIDATION_MODE`.
     */
    public function mode(): string
    {
        $configured = config('ai.tool_schema_validation.mode', 'warn');

        return $configured === 'strict' ? 'strict' : 'warn';
    }
}
