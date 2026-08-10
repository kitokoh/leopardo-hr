<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S-3 (#1663) — Fix ADDITIF : `kiosk_announcements.company_id` en UUID.
 *
 * Contexte : la migration `2026_05_18_000003_create_zkteco_devices_table`
 * a été modifiée RÉTROACTIVEMENT (commit 0e82521a, F-13b) pour créer
 * `kiosk_announcements.company_id` en `uuid` au lieu de `unsignedBigInteger`
 * (companies.id est un UUID ; un bigint casse l'isolation tenant). Les
 * environnements déjà migrés avec l'ancienne version gardent une colonne
 * bigint — cette migration la convertit, de façon additive et sûre :
 *
 *   1. no-op si la colonne est déjà uuid (env neuf) ;
 *   2. conversion `bigint → uuid` avec cast texte uniquement si toutes les
 *      lignes existantes sont des UUID valides (aucune perte de données ;
 *      des valeurs non-UUID dans cette colonne seraient déjà cassées côté
 *      isolation tenant et ne peuvent pas être devinées).
 *
 * `zkteco_devices.company_id` reste bigint volontairement : aucune FK vers
 * companies, aucune donnée à migrer, hors périmètre de la spec.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('kiosk_announcements')) {
            return;
        }

        $column = DB::selectOne("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_name = 'kiosk_announcements'
              AND column_name = 'company_id'
              AND table_schema = current_schema()
        ");

        if ($column === null || $column->data_type === 'uuid') {
            return; // Rien à faire (env neuf ou déjà corrigé).
        }

        // Données existantes : on ne convertit que si tout est castable en UUID.
        $invalid = DB::selectOne("
            SELECT 1
            FROM kiosk_announcements
            WHERE company_id IS NOT NULL
              AND company_id::text !~ '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'
            LIMIT 1
        ");

        if ($invalid !== null) {
            // Valeurs non-UUID présentes : impossibles à convertir sans
            // deviner des données — on laisse la colonne en l'état (les
            // lignes concernées sont déjà hors isolation tenant).
            return;
        }

        DB::statement(
            'ALTER TABLE kiosk_announcements ALTER COLUMN company_id TYPE uuid USING company_id::text::uuid'
        );

        DB::statement(
            'DROP INDEX IF EXISTS kiosk_announcements_company_id_is_active_starts_at_index'
        );

        DB::statement(
            'CREATE INDEX kiosk_announcements_company_id_is_active_starts_at_index '
            .'ON kiosk_announcements (company_id, is_active, starts_at)'
        );
    }

    public function down(): void
    {
        // Conversion inverse uuid → bigint perdrait des UUID — no-op assumé.
    }
};
