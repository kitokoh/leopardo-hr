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
 *     `ValueError` à la lecture → une ligne illisible fait échouer TOUTE la
 *     page de journal) ;
 *   - créer un transfert source = destination (mouvement sans effet) ou de
 *     quantité nulle/négative.
 *
 * Migration additive et idempotente (même garde `pg_constraint` que la
 * Pharmacie) : rejouable sans erreur.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stock jamais négatif.
        DB::statement(
            "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'retail_stock_levels_quantity_check') "
            .'THEN ALTER TABLE retail_stock_levels ADD CONSTRAINT retail_stock_levels_quantity_check '
            .'CHECK (quantity >= 0); END IF; END $$'
        );

        // reason_code toujours dans l'énumération applicative.
        DB::statement(
            "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'retail_inventory_movements_reason_code_check') "
            .'THEN ALTER TABLE retail_inventory_movements ADD CONSTRAINT retail_inventory_movements_reason_code_check '
            ."CHECK (reason_code IN ('purchase','sale','adjustment','return','transfer_in','transfer_out','loss')) "
            .'END IF; END $$'
        );

        // Un transfert déplace réellement quelque chose, vers un autre lieu.
        DB::statement(
            "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'retail_stock_transfers_distinct_locations_check') "
            .'THEN ALTER TABLE retail_stock_transfers ADD CONSTRAINT retail_stock_transfers_distinct_locations_check '
            .'CHECK (from_location_id <> to_location_id); END IF; END $$'
        );

        DB::statement(
            "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'retail_stock_transfers_quantity_check') "
            .'THEN ALTER TABLE retail_stock_transfers ADD CONSTRAINT retail_stock_transfers_quantity_check '
            .'CHECK (quantity > 0); END IF; END $$'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE retail_stock_levels DROP CONSTRAINT IF EXISTS retail_stock_levels_quantity_check');
        DB::statement('ALTER TABLE retail_inventory_movements DROP CONSTRAINT IF EXISTS retail_inventory_movements_reason_code_check');
        DB::statement('ALTER TABLE retail_stock_transfers DROP CONSTRAINT IF EXISTS retail_stock_transfers_distinct_locations_check');
        DB::statement('ALTER TABLE retail_stock_transfers DROP CONSTRAINT IF EXISTS retail_stock_transfers_quantity_check');
    }
};
