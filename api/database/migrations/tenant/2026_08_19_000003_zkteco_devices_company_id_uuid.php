<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F-13b / #1663 — `zkteco_devices.company_id` passe de `unsignedBigInteger`
 * à `uuid` pour matcher `companies.id` (uuid).
 *
 * Le code stocke `$company->id` (UUID) dans cette colonne depuis toujours
 * (registerDevice → `company_id => $companyId`) : sur schéma réel,
 * l'insertion d'un UUID dans une colonne bigint échoue en
 * `SQLSTATE[22P02] invalid input syntax for type bigint` → impossible
 * d'enregistrer un kiosque en production. Le test F-13 aligné sur les vraies
 * migrations (ZktecoSyncMethodEnforcementTest, RefreshTenantDatabase) l'a
 * exposé.
 *
 * Même pattern que `2026_08_09_000007_kiosk_announcements_company_id_uuid` :
 * no-op si déjà uuid ; conversion cast texte uniquement si toutes les valeurs
 * existantes sont des UUID valides (sinon on laisse — lignes hors isolation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('zkteco_devices')) {
            return;
        }

        $column = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_name = 'zkteco_devices'
              AND column_name = 'company_id'
              AND table_schema = current_schema()
        ");

        if ($column === null || $column->data_type === 'uuid') {
            return; // Env neuf (migration create corrigée) ou déjà converti.
        }

        // Données existantes : on ne convertit que si tout est castable en UUID.
        $invalid = DB::selectOne("
            SELECT 1
            FROM zkteco_devices
            WHERE company_id IS NOT NULL
              AND company_id::text !~ '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'
            LIMIT 1
        ");

        if ($invalid !== null) {
            // Valeurs non-UUID présentes : impossibles à convertir sans
            // deviner des données — on laisse la colonne en l'état.
            return;
        }

        DB::statement(
            'ALTER TABLE zkteco_devices ALTER COLUMN company_id TYPE uuid USING company_id::text::uuid'
        );

        DB::statement(
            'DROP INDEX IF EXISTS zkteco_devices_company_id_status_index'
        );

        DB::statement(
            'CREATE INDEX zkteco_devices_company_id_status_index '
            .'ON zkteco_devices (company_id, status)'
        );
    }

    public function down(): void
    {
        // Conversion destructive (uuid → bigint impossible en général) :
        // pas de down — le pattern kiosk_announcements est identique.
    }
};
