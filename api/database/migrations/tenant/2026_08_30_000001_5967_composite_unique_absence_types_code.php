<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #5967 — `absence_types_code_unique` était posé sur `code` SEUL alors
 * que `absence_types` vit dans le schéma partagé `shared_tenants` : le premier
 * tenant à seeder ses codes standards (CA, MAL, MAT, PAT, CSS, INT, CHOM) "gagne"
 * l'unicité globale, et `SectorTemplateService::seedAbsenceTypes()` voit tous
 * ses `insertOrIgnore()` suivants silencieusement ignorés pour tout autre
 * tenant → aucun type d'absence standard → onboarding congés cassé.
 *
 * Correctif : remplacer l'index unique global par un index unique composite
 * (company_id, code) — chaque tenant peut désormais avoir ses propres codes
 * standards sans collision inter-tenant, tout en gardant l'unicité intra-tenant.
 *
 * Sécurité : si des doublons (company_id, code) existent déjà (ne devrait pas
 * arriver — l'unicité globale empêchait justement les doublons de code), la
 * création de l'index composite échoue explicitement (migration rouge) plutôt
 * que de supprimer silencieusement des lignes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('absence_types') || ! schemaHasColumn('absence_types', 'company_id')) {
            return;
        }

        // Retrait de l'index unique global sur `code` seul (nom par défaut
        // Laravel pour `$table->string('code', 20)->unique()`).
        DB::statement('DROP INDEX IF EXISTS absence_types_code_unique');

        // Index unique composite (company_id, code) — pas de dédoublonnage
        // préalable nécessaire : l'ancien index global empêchait déjà tout
        // doublon de `code`, donc aucune paire (company_id, code) ne peut
        // être dupliquée aujourd'hui. Si c'était le cas malgré tout, cette
        // instruction échoue explicitement (fail loud, pas de perte silencieuse).
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS absence_types_company_id_code_unique
            ON absence_types (company_id, code)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS absence_types_company_id_code_unique');

        if (schemaTableExists('absence_types')) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS absence_types_code_unique
                ON absence_types (code)'
            );
        }
    }
};
