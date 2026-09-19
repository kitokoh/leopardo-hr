<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7739 — Rattachement des réservations marketplace aux comptes clients
 * grand public (épic #7736).
 *
 * `customer_account_id` référence `travel_customer_accounts` (table
 * PLATEFORME, schéma public) : colonne nue SANS contrainte FK — une migration
 * tenant ne crée jamais de FK vers une table public (constitution §II, même
 * règle que l'interdiction de FK vers `companies`). Nullable : le checkout
 * invité reste possible (critère #7739).
 *
 * Idempotente (guards schemaTableExists/schemaHasColumn + schéma résolu,
 * patterns #1613/#1962).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_bookings')) {
            return;
        }

        $schema = resolveTableSchema('travel_bookings');

        if (! schemaHasColumn('travel_bookings', 'customer_account_id')) {
            Schema::table("{$schema}.travel_bookings", function (Blueprint $table): void {
                $table->unsignedBigInteger('customer_account_id')->nullable()->after('customer_contact_id');
                // « Mes réservations » : lookup cross-agences par compte.
                $table->index(['customer_account_id']);
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_bookings')) {
            return;
        }

        $schema = resolveTableSchema('travel_bookings');

        if (schemaHasColumn('travel_bookings', 'customer_account_id')) {
            Schema::table("{$schema}.travel_bookings", function (Blueprint $table): void {
                $table->dropIndex(['customer_account_id']);
                $table->dropColumn('customer_account_id');
            });
        }
    }
};
