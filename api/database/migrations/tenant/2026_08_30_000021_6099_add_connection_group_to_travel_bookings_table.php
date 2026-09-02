<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6099 (TRAVEL-809) — Correspondances : groupe de liaison des jambes.
 *
 * Un voyage avec changement = deux réservations indépendantes (billets
 * séparés) reliées par `connection_group_id` ; l'annulation reste possible
 * par jambe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaHasColumn('travel_bookings', 'connection_group_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->uuid('connection_group_id')->nullable();
                $table->index(['company_id', 'connection_group_id'], 'travel_bookings_company_connection_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropIndex('travel_bookings_company_connection_idx');
            $table->dropColumn('connection_group_id');
        });
    }
};
