<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #9299 — Contraintes CHECK sur le stock Retail (défense en profondeur).
 *
 * La verticale Pharmacie pose ses invariants EN BASE (migration
 * `2026_09_22_100002_7800`) : `quantity >= 0` sur les lots, `type IN (...)`
 * sur les mouvements. La verticale Retail, elle, ne s'appuyait que sur le
 * service applicatif — une défense en profondeur à un seul étage.
 *
 * Une migration de données, un import, un script de maintenance ou un futur
 * chemin d'écriture qui contourne `RetailStockService` pouvait donc :
 *   - rendre un stock négatif (casse la valorisation et la réconciliation) ;
 *   - écrire un `reason_code` hors énumération (le cast Eloquent lève alors
 *     à la lecture → une ligne illisible fait échouer TOUTE la page de
 *     journal) ;
 *   - créer un transfert source = destination (mouvement sans effet) ou de
 *     quantité nulle/négative.
 *
 * Gardes F-17 : `schemaTableExists()`, migration additive et idempotente
 * (même garde `pg_constraint` que la Pharmacie) — rejouable sans erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addCheck(
            table: 'retail_stock_levels',
            constraint: 'retail_stock_levels_quantity_check',
            expression: 'quantity >= 0',
        );

        $this->addCheck(
            table: 'retail_inventory_movements',
            constraint: 'retail_inventory_movements_reason_code_check',
            expression: "reason_code IN ('purchase','sale','adjustment','return','transfer_in','transfer_out','loss')",
        );

        $this->addCheck(
            table: 'retail_stock_transfers',
            constraint: 'retail_stock_transfers_distinct_locations_check',
            expression: 'from_location_id <> to_location_id',
        );

        $this->addCheck(
            table: 'retail_stock_transfers',
            constraint: 'retail_stock_transfers_quantity_check',
            expression: 'quantity > 0',
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE retail_stock_levels DROP CONSTRAINT IF EXISTS retail_stock_levels_quantity_check');
        DB::statement('ALTER TABLE retail_inventory_movements DROP CONSTRAINT IF EXISTS retail_inventory_movements_reason_code_check');
        DB::statement('ALTER TABLE retail_stock_transfers DROP CONSTRAINT IF EXISTS retail_stock_transfers_distinct_locations_check');
        DB::statement('ALTER TABLE retail_stock_transfers DROP CONSTRAINT IF EXISTS retail_stock_transfers_quantity_check');
    }

    /**
     * Ajoute une contrainte CHECK de façon idempotente, en qualifiant la table
     * par son schéma réel (même pattern que la migration Pharmacie #7800).
     */
    private function addCheck(string $table, string $constraint, string $expression): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $schema = resolveTableSchema($table);
        $qualified = $schema !== null ? "\"{$schema}\".\"{$table}\"" : "\"{$table}\"";

        DB::statement(
            "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = '{$constraint}') "
            ."THEN ALTER TABLE {$qualified} ADD CONSTRAINT {$constraint} "
            ."CHECK ({$expression}); END IF; END $$"
        );
    }
};
