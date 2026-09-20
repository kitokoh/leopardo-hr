<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 (BC-17 RETAIL, epic Leopardo Marche) — Rattachement OPTIONNEL des
 * commandes en ligne a un compte acheteur marketplace.
 *
 * `buyer_id` nullable : le checkout invite (sans compte) reste la norme ;
 * quand l'acheteur est authentifie au moment du POST /public/market/orders,
 * la commande lui est liee pour l'historique cross-tenant
 * (GET /public/market/account/orders) et les avis verifies.
 *
 * PAS de FK (les comptes acheteurs vivent dans le schema public, la table
 * `retail_orders` dans les schemas tenants — cross-schema). ALTER
 * idempotent (hasColumn), index nomme hors Blueprint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('retail_orders', 'buyer_id')) {
                $table->unsignedBigInteger('buyer_id')->nullable();
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS retail_orders_buyer_id_idx ON retail_orders (buyer_id)');
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS retail_orders_buyer_id_idx');

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('retail_orders', 'buyer_id')) {
                $table->dropColumn('buyer_id');
            }
        });
    }
};
