<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BC-17 RETAIL (#9270, #9269) — deux colonnes manquantes sur le journal des
 * mouvements de stock Retail.
 *
 * **(1) `idempotency_key` (#9270)** — le POS tablette est offline-first : une
 * opération mise en file est rejouée après reconnexion. Les PAIEMENTS sont
 * dédupliqués par une clé stable (`PosSyncEngine`), les MOUVEMENTS DE STOCK ne
 * l'étaient pas : un rejeu de réception doublait la quantité. La clé est
 * nullable (les mouvements existants et les appels web n'en ont pas) et
 * unique PAR TENANT — deux tenants peuvent employer la même clé sans
 * collision.
 *
 * L'unicité est portée par un index PARTIEL (`WHERE idempotency_key IS NOT
 * NULL`) : un index unique complet sur une colonne majoritairement NULL
 * stockerait une entrée par ligne pour rien, et PostgreSQL autorise plusieurs
 * NULL dans un index unique — mais l'index partiel dit explicitement
 * l'intention.
 *
 * **(2) `unit_cost_minor` (#9269)** — coût d'acquisition du mouvement, base du
 * coût moyen pondéré. `retail_stock_levels.avg_cost_minor` existait depuis
 * #7673 sans jamais être écrit : la valorisation du stock utilisait le coût
 * CATALOGUE courant, donc une hausse de prix fournisseur réécrivait
 * rétroactivement la valeur du stock déjà détenu.
 *
 * Migration ADDITIVE : aucune colonne existante n'est modifiée, `down()` ne
 * détruit que ce que ce fichier a créé.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_inventory_movements')) {
            return;
        }

        Schema::table('retail_inventory_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('retail_inventory_movements', 'idempotency_key')) {
                $table->string('idempotency_key', 120)->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('retail_inventory_movements', 'unit_cost_minor')) {
                $table->unsignedBigInteger('unit_cost_minor')->nullable()->after('quantity_delta');
            }
        });

        // Index unique PARTIEL : seules les lignes portant une clé sont
        // contraintes. `IF NOT EXISTS` rend la migration rejouable.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS retail_inventory_movements_company_idempotency_unique '
            .'ON retail_inventory_movements (company_id, idempotency_key) '
            .'WHERE idempotency_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_inventory_movements')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS retail_inventory_movements_company_idempotency_unique');

        Schema::table('retail_inventory_movements', function (Blueprint $table): void {
            foreach (['idempotency_key', 'unit_cost_minor'] as $column) {
                if (Schema::hasColumn('retail_inventory_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
