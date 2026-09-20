<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7812 (BC-17 RETAIL, epic Leopardo Marche) - Paiement en ligne : champs
 * de paiement sur `retail_orders` (nullables — POS et commandes historiques
 * non concernes).
 *
 * - `payment_method` : moyen choisi au checkout public (`cash` COD par
 *   defaut, `online` via PSP) — valeurs du RetailPaymentMethod existant ;
 * - `payment_status` : `pending|paid|refunded` — passe a `paid` UNIQUEMENT
 *   par la voie signee (webhook verifie ou reconciliation provider), a
 *   `refunded` par le remboursement vendeur ;
 * - `paid_at` : horodatage de l'encaissement en ligne (auditable, en
 *   complement de l'enregistrement RetailOrderPayment cree au succes).
 *
 * ALTER idempotent via Schema::hasColumn, sans FK, jamais de second
 * Schema::create (garde #7452). down() complet avec gardes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('retail_orders', 'payment_method')) {
                $table->string('payment_method', 20)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'payment_status')) {
                $table->string('payment_status', 20)->nullable();
            }

            if (! Schema::hasColumn('retail_orders', 'paid_at')) {
                $table->timestamp('paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        Schema::table('retail_orders', function (Blueprint $table): void {
            foreach (['paid_at', 'payment_status', 'payment_method'] as $column) {
                if (Schema::hasColumn('retail_orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
