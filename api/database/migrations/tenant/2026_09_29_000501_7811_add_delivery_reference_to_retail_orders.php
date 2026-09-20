<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7811 (BC-17 RETAIL, epic Leopardo Marche) - Handoff livraison BC-26 :
 * la reference de la livraison (`DLV-YYYY-NNNNNN`) creee automatiquement a
 * la confirmation d'une commande en ligne est stockee sur `retail_orders`
 * (`delivery_reference`, nullable — POS et commandes pre-handoff) pour etre
 * partagee sur la page de suivi publique.
 *
 * Integration par evenements (registre BC) : Retail n'ecrit JAMAIS dans les
 * tables Delivery — cette colonne est alimentee par le listener Retail du
 * retour d'evenement `RetailOnlineOrderDeliveryCreated`.
 *
 * Sans FK, idempotente via Schema::hasColumn (conventions migrations tenant
 * §2.6). down() complet avec garde.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('retail_orders', 'delivery_reference')) {
                $table->string('delivery_reference', 30)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (Schema::hasColumn('retail_orders', 'delivery_reference')) {
                $table->dropColumn('delivery_reference');
            }
        });
    }
};
