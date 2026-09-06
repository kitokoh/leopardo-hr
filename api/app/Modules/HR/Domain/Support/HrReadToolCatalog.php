<?php

declare(strict_types=1);

namespace App\Modules\HR\Domain\Support;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B1 (#6854) — catalogue des outils de LECTURE HR déclarés au contrat A3
 * (BC-23, #6850, EPIC #6846).
 *
 * Premier BC à exposer ses outils via AIToolDefinition : trois agrégats
 * lecture seule (sensibilité `read`), implémentés par les handlers
 * IntentEngine homonymes qui réutilisent les modèles canoniques HR/Planning
 * (Employee + scopes de visibilité, Absence et LeaveBalance du propriétaire
 * Planning, PA2-ARCH-002) — aucune logique dupliquée, sorties agrégées,
 * jamais de données brutes sensibles (privacy A6, #6853).
 *
 * Enregistrées par HRServiceProvider::boot() dans AIToolDefinitionRegistry ;
 * l'hôte BC-23 (ToolRegistry) enrichit les entrées `ai_tool_registry`
 * homonymes sans changer leur comportement (tranche additive A3).
 */
final class HrReadToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'team_overview',
                description: "Vue agrégée de l'effectif (entreprise ou périmètre du manager) : total, répartition par statut, type de contrat et département. Aucune donnée nominative.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'department_id' => [
                            'type' => 'integer',
                            'description' => 'Filtre optionnel sur un département.',
                        ],
                    ],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'scope' => ['type' => 'string', 'enum' => ['company', 'team', 'self']],
                        'total' => ['type' => 'integer'],
                        'by_status' => ['type' => 'object'],
                        'by_contract_type' => ['type' => 'object'],
                        'by_department' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'department' => ['type' => 'string'],
                                    'count' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
                permission: 'employees.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-04',
                version: 1,
            ),
            new AIToolDefinition(
                name: 'team_absences_recent',
                description: "Absences récentes du périmètre du manager sur une période (défaut : 30 derniers jours), avec statuts et agrégats. Sortie non nominative (identifiants employés uniquement).",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'status' => [
                            'type' => 'string',
                            'enum' => ['pending', 'approved', 'rejected', 'cancelled'],
                            'description' => 'Filtre optionnel sur le statut.',
                        ],
                        'from' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Début de période (YYYY-MM-DD).',
                        ],
                        'to' => [
                            'type' => 'string',
                            'format' => 'date',
                            'description' => 'Fin de période (YYYY-MM-DD).',
                        ],
                    ],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'period' => ['type' => 'object'],
                        'total' => ['type' => 'integer'],
                        'count' => ['type' => 'integer'],
                        'by_status' => ['type' => 'object'],
                        'absences' => ['type' => 'array'],
                    ],
                ],
                permission: 'absences.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-04',
                version: 1,
            ),
            new AIToolDefinition(
                name: 'employee_leave_balance',
                description: "Soldes de congés d'un employé pour une année (défaut : année courante) : solde, utilisé et en attente par type d'absence. Un employé ne consulte que son propre solde.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => [
                            'type' => 'integer',
                            'description' => 'Employé concerné (défaut : l\'appelant). Réservé aux managers.',
                        ],
                        'year' => [
                            'type' => 'integer',
                            'description' => 'Année du solde (défaut : année courante).',
                        ],
                    ],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer'],
                        'year' => ['type' => 'integer'],
                        'count' => ['type' => 'integer'],
                        'leave_balances' => ['type' => 'array'],
                    ],
                ],
                permission: 'leave.view',
                sensitivity: AIToolSensitivity::Read,
                bc: 'BC-04',
                version: 1,
            ),
        ];
    }
}
