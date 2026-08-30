<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6211 (RESTO-606) - RestaurantManager : idempotence du credit de points.
 *
 * Critere d'acceptation RESTO-606 : « Points credits une seule fois par
 * commande payee ». L'unique partiel (company_id, customer_id, order_id)
 * restreint aux mouvements de gain (reason_code = 'earn') avec commande :
 * le rejeu du consommateur d'outbox ne peut pas crediter deux fois la meme
 * commande, meme en cas de course entre deux workers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_loyalty_points_movements')) {
            return;
        }

        DB::statement(
            'CREATE UNIQUE INDEX restaurant_loyalty_movements_earn_order_unique '
            .'ON restaurant_loyalty_points_movements (company_id, customer_id, order_id) '
            ."WHERE reason_code = 'earn' AND order_id IS NOT NULL",
        );

        DB::statement('COMMENT ON INDEX restaurant_loyalty_movements_earn_order_unique IS \'Credit de points unique par (tenant, client, commande) - raison earn (RESTO-606/#6211).\';');
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_loyalty_points_movements')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS restaurant_loyalty_movements_earn_order_unique');
    }
};
