<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6210 (RESTO-605) - RestaurantManager : une seule livraison par commande.
 *
 * La spec (§3.5) garantit qu'une commande n'a qu'une livraison : l'index
 * simple (company_id, order_id) de RESTO-211 est renforce en index UNIQUE
 * tenant-scope pour rendre la creation de livraison idempotente au niveau
 * base (le rejeu d'une creation sur la meme commande echoue proprement).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_deliveries')) {
            return;
        }

        Schema::table('restaurant_deliveries', function (Blueprint $table): void {
            $table->dropIndex('restaurant_deliveries_company_order_idx');
            $table->unique(['company_id', 'order_id'], 'restaurant_deliveries_company_order_unique');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_deliveries')) {
            return;
        }

        Schema::table('restaurant_deliveries', function (Blueprint $table): void {
            $table->dropUnique('restaurant_deliveries_company_order_unique');
            $table->index(['company_id', 'order_id'], 'restaurant_deliveries_company_order_idx');
        });
    }
};
