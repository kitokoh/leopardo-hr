<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7814 — Rattachement des commandes en ligne Leopardo Marché aux comptes
 * acheteurs grand public.
 *
 * `customer_account_id` référence `market_customer_accounts` (table
 * PLATEFORME, schéma public) : colonne nue SANS contrainte FK — une migration
 * tenant ne crée jamais de FK vers une table public (constitution §II, même
 * règle que #7739 pour travel_bookings). Nullable : le checkout invité reste
 * possible (critère #7814).
 *
 * Idempotente (guards schemaTableExists/schemaHasColumn + schéma résolu,
 * patterns #1613/#1962).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        $schema = resolveTableSchema('retail_orders');

        if (! schemaHasColumn('retail_orders', 'customer_account_id')) {
            Schema::table("{$schema}.retail_orders", function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_account_id')->nullable()->after('customer_email');
                // « Mes commandes » : lookup cross-boutiques par compte.
                $table->index(['customer_account_id']);
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('retail_orders')) {
            return;
        }

        $schema = resolveTableSchema('retail_orders');

        if (schemaHasColumn('retail_orders', 'customer_account_id')) {
            Schema::table("{$schema}.retail_orders", function (Blueprint $table): void {
                $table->dropIndex(['customer_account_id']);
                $table->dropColumn('customer_account_id');
            });
        }
    }
};
