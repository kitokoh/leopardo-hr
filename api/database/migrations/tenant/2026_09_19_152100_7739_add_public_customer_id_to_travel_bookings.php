<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #7739 — Rattachement des réservations au compte client grand public.
 *
 * `public_customer_id` référence PAR VALEUR `public.travel_public_customers`
 * (table cross-tenant du schéma public) — aucune FK inter-schémas, même
 * décision que le pont RH ↔ voyage (#7638). Nullable : le checkout INVITÉ
 * reste possible, seule une réservation revendiquée porte l'identifiant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_bookings') && ! Schema::hasColumn('travel_bookings', 'public_customer_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->unsignedBigInteger('public_customer_id')->nullable();
                $table->index(['public_customer_id'], 'travel_bookings_public_customer_idx');
            });
        }
    }

    public function down(): void
    {
        if (schemaTableExists('travel_bookings') && Schema::hasColumn('travel_bookings', 'public_customer_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->dropIndex('travel_bookings_public_customer_idx');
                $table->dropColumn('public_customer_id');
            });
        }
    }
};
