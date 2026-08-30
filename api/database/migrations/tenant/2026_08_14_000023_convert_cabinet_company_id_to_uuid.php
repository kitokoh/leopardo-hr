<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vague QA 2026-08-14 — le module Cabinet stockait `company_id` en BIGINT
 * avec un hack legacy (`legacyCompanyKey()` → 0 pour les entreprises UUID).
 *
 * Conséquences :
 *   1. Chaque entreprise UUID écrivait `company_id = 0` → colonne sans
 *      signification multi-tenant (toutes les entreprises partagent la clé 0).
 *   2. `CabinetDocument::create(['company_id' => $uuid])` (tests #1921,
 *      imports directs) échouait : `invalid input syntax for type bigint`.
 *   3. Les 4 tests d'immuabilité read_only (#1921) étaient rouges sur main.
 *
 * Correctif racine : `company_id` passe en UUID (les entreprises sont en
 * UUID depuis toujours — `HasUuids`). Les lignes legacy `company_id = 0`
 * sont des orphelins sans tenant : leur valeur est mise à NULL (colonne
 * désormais nullable) — aucun fichier n'est supprimé, l'accès reste scopé
 * par `employee_id` (contrats existants).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['cabinet_folders', 'cabinet_documents', 'cabinet_shares'] as $table) {
            if (! schemaTableExists($table)) {
                continue;
            }

            if (! schemaHasColumn($table, 'company_id')) {
                continue;
            }

            $columnType = DB::selectOne(
                "SELECT data_type FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = 'company_id'",
                [$table]
            )?->data_type;

            if ($columnType === 'uuid') {
                continue;
            }

            DB::statement("ALTER TABLE {$table} ALTER COLUMN company_id DROP NOT NULL");
            DB::statement("UPDATE {$table} SET company_id = NULL WHERE company_id = 0");
            DB::statement("ALTER TABLE {$table} ALTER COLUMN company_id TYPE uuid USING NULL");
        }
    }

    public function down(): void
    {
        // Pas de retour arrière automatique : les données legacy ont été
        // normalisées (0 → NULL) et le type uuid est le contrat actuel.
    }
};
