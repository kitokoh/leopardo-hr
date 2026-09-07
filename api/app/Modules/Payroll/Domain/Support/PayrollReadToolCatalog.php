<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Domain\Support;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B2 (#6855) — catalogue des outils de LECTURE Payroll déclarés au contrat A3
 * (BC-23, #6850, EPIC #6846).
 *
 * Deuxième BC à exposer ses outils via AIToolDefinition : un agrégat lecture
 * seule (sensibilité `read`) — le statut de la période/run de paie en cours.
 * Implémenté par le handler IntentEngine homonyme qui réutilise les modèles
 * canoniques Payroll (`PayrollRun`, `PaySlip`) — aucune logique dupliquée.
 * Sortie AGRÉGÉE : statuts, dates et compteurs uniquement — jamais de
 * montants (nominatifs ou totaux), jamais de bulletins (privacy A6, #6853).
 *
 * Enregistrées par PayrollServiceProvider::boot() dans
 * AIToolDefinitionRegistry ; l'hôte BC-23 (ToolRegistry) enrichit l'entrée
 * `ai_tool_registry` homonyme sans changer son comportement (tranche A3).
 */
final class PayrollReadToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'payroll_current_status',
                description: "Statut agrégé de la paie du tenant : dernier run clôturé (validé/payé) et run en cours éventuel (draft/calculating/processing/calculated/error) avec progression (bulletins générés/validés rapportés à l'effectif du run). Aucun montant, aucune donnée nominative.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'has_current_run' => ['type' => 'boolean'],
                        // `current_run` / `last_closed_run` : objet, ou null
                        // quand aucun run de la catégorie n'existe.
                        'current_run' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'status' => ['type' => 'string'],
                                'period' => ['type' => 'object'],
                                'employee_count' => ['type' => 'integer'],
                                'slips_count' => ['type' => 'integer'],
                                'validated_slips_count' => ['type' => 'integer'],
                                'calculated_at' => ['type' => 'string'],
                                'updated_at' => ['type' => 'string'],
                            ],
                        ],
                        'last_closed_run' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'status' => ['type' => 'string'],
                                'period' => ['type' => 'object'],
                                'employee_count' => ['type' => 'integer'],
                                'slips_count' => ['type' => 'integer'],
                                'validated_slips_count' => ['type' => 'integer'],
                                'validated_at' => ['type' => 'string'],
                                'paid_at' => ['type' => 'string'],
                            ],
                        ],
                        'as_of' => ['type' => 'string'],
                    ],
                ],
                permission: 'payroll.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-07',
                version: 1,
            ),
        ];
    }
}
