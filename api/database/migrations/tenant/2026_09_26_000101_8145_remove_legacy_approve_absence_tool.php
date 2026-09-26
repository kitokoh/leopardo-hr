<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BOS-004 (#8145) — retire du registre la ligne legacy `approve_absence`.
 *
 * Depuis #6856 le chemin canonique d'approbation/refus d'absence via l'IA est
 * `absence_decision` (Actions Planning `ApproveAbsence`/`RejectAbsence`, qui
 * émettent `AbsenceApproved`/`AbsenceRejected`). L'ancien outil `approve_absence`
 * faisait un `->update()` direct en base : il contournait l'Action canonique et
 * n'émettait AUCUN événement métier — les consommateurs (notifications,
 * compteurs) étaient silencieusement ignorés quand l'approbation venait de l'IA.
 *
 * Le seeder `AIToolRegistrySeeder` utilise `updateOrInsert` : retirer la ligne
 * du seeder ne supprime PAS une ligne déjà écrite en base. D'où cette migration
 * de nettoyage (tenant), idempotente et réversible.
 */
return new class extends Migration
{
    private const LEGACY_TOOL = 'approve_absence';

    public function up(): void
    {
        if (! schemaTableExists('ai_tool_registry')) {
            return;
        }

        DB::table('ai_tool_registry')
            ->where('name', self::LEGACY_TOOL)
            ->delete();
    }

    public function down(): void
    {
        if (! schemaTableExists('ai_tool_registry')) {
            return;
        }

        // Restaure la ligne legacy à l'identique du seeder d'avant #8145 —
        // une seule fois (updateOrInsert), même si up() n'avait rien supprimé.
        // Le handler PHP n'existe plus : la ligne restaurée reste inerte, elle
        // ne sert qu'à revenir à l'état de registre antérieur.
        DB::table('ai_tool_registry')->updateOrInsert(
            ['name' => self::LEGACY_TOOL],
            [
                'description' => 'Approve a pending absence request.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => ['type' => 'integer'],
                    ],
                    'required' => ['absence_id'],
                ]),
                'required_permissions' => json_encode(['absences.approve']),
                'required_role' => 'manager',
                'module' => 'rh',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
