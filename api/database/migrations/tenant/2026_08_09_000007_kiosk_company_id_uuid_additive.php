<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec S-3 (#1663) — Durcissement paie : `company_id` UUID sur kiosque/ZKTeco.
 *
 * Migration ADDITIVE de rattrapage : `2026_05_18_000003_create_zkteco_devices_table`
 * a été corrigée rétroactivement (F-13b) pour `kiosk_announcements.company_id`
 * (uuid au lieu de bigint) ; les env déjà migrés ne rejoueront pas ce code, et
 * `zkteco_devices.company_id` reste bigint partout. `companies.id` étant un
 * UUID, une colonne bigint empêche toute référence réelle et casse l'isolation
 * tenant.
 *
 * Cette migration convertit en UUID les colonnes `company_id` de
 * `zkteco_devices` et `kiosk_announcements` si elles ne le sont pas déjà :
 *   - les valeurs qui ne sont pas des UUID valides (anciennes lignes bigint)
 *     sont neutralisées (NULL) avant conversion — le kiosque/annonces étant
 *     fail-open, aucune perte fonctionnelle ;
 *   - idempotente : les env neufs (déjà uuid) ne sont pas touchés.
 */
return new class extends Migration
{
    private const TABLES = ['zkteco_devices', 'kiosk_announcements'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue; // Table absente dans ce contexte.
            }

            $column = DB::selectOne(
                'SELECT data_type
                   FROM information_schema.columns
                  WHERE table_schema = ?
                    AND table_name = ?
                    AND column_name = ?',
                [$schema, $table, 'company_id']
            );

            if ($column === null) {
                continue; // Colonne absente — rien à convertir.
            }

            if ($column->data_type === 'uuid') {
                continue; // Déjà conforme (env neufs / kiosk_announcements corrigé).
            }

            $quoted = "\"{$schema}\".\"{$table}\"";

            // Neutraliser les valeurs non-UUID avant conversion de type.
            DB::statement("ALTER TABLE {$quoted} ALTER COLUMN company_id DROP NOT NULL");
            DB::statement(
                "UPDATE {$quoted}
                    SET company_id = NULL
                  WHERE company_id IS NOT NULL
                    AND company_id::text !~ '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'"
            );
            DB::statement(
                "ALTER TABLE {$quoted} ALTER COLUMN company_id TYPE uuid USING company_id::text::uuid"
            );
        }
    }

    public function down(): void
    {
        // Rétrograde bigint impossible sans perte d'information — migration additive assumée.
    }
};
