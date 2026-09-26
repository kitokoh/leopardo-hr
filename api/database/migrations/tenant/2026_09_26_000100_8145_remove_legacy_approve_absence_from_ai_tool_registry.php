<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #8145 (BOS-004) — nettoyage du write-tool legacy `approve_absence` dans les
 * registres `ai_tool_registry` des bases existantes.
 *
 * Le handler legacy faisait un `update()` direct en base (sans l'Action
 * canonique `ApproveAbsence`, sans événement `AbsenceApproved`) : il est
 * retiré du code dans la même PR (WriteActionRunner, config ai.write_tools /
 * ai.tool_permissions, IntentEngine, seeder). Cette migration supprime la
 * ligne registre pour qu'aucune base ne continue d'exposer le tool au LLM —
 * un tool exposé sans handler ferait « promettre » l'action puis échouer
 * (#5625).
 *
 * Migration de DONNÉES uniquement, réentrante (garde schemaTableExists),
 * `down()` réversible (réinsertion de la ligne, définition identique au
 * seeder d'avant #8145 — rollback documenté dans l'issue).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('ai_tool_registry')) {
            return;
        }

        DB::table('ai_tool_registry')->where('name', 'approve_absence')->delete();
    }

    public function down(): void
    {
        if (! schemaTableExists('ai_tool_registry')) {
            return;
        }

        DB::table('ai_tool_registry')->updateOrInsert(
            ['name' => 'approve_absence'],
            [
                'name' => 'approve_absence',
                'description' => 'Approve a pending absence request.',
                'parameters' => json_encode([
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => ['type' => 'integer'],
                    ],
                    'required' => ['absence_id'],
                ]),
                'required_permissions' => '["absences.approve"]',
                'required_role' => 'manager',
                'module' => 'rh',
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
