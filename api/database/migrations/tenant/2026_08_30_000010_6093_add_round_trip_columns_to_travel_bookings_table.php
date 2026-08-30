<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6093 (TRAVEL-802) — Aller-retour : liaison des deux jambes.
 *
 * `round_trip_group_id` (uuid partagé) groupe les deux réservations,
 * `return_booking_id` pointe de l'aller vers le retour, `leg` qualifie la
 * jambe (outbound|return). L'annulation reste possible PAR SENS : les deux
 * réservations sont indépendantes dans leur cycle de vie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaHasColumn('travel_bookings', 'round_trip_group_id')) {
            Schema::table('travel_bookings', function (Blueprint $table): void {
                $table->uuid('round_trip_group_id')->nullable();
                $table->unsignedBigInteger('return_booking_id')->nullable();
                $table->string('leg', 10)->nullable();

                $table->index(['company_id', 'round_trip_group_id'], 'travel_bookings_company_group_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_bookings', function (Blueprint $table): void {
            $table->dropIndex('travel_bookings_company_group_idx');
            $table->dropColumn(['round_trip_group_id', 'return_booking_id', 'leg']);
        });
    }
};
