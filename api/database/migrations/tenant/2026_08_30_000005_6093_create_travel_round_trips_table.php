<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6093 (TRAVEL-802) — travel_round_trips : aller-retour combiné.
 *
 * Lie deux réservations (aller + retour) d'un même tenant. Chaque sens reste
 * une réservation standard (annulable/remboursable par sens) ; la table
 * porte uniquement le lien + l'idempotence de création du combo. Le statut
 * est dérivé (voir RoundTripStatus) — aucune colonne redondante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_round_trips')) {
            Schema::create('travel_round_trips', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('booking_outbound_id');
                $table->unsignedBigInteger('booking_return_id');
                $table->string('idempotency_key', 255);
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'travel_round_trips_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'travel_round_trips_company_idempotency_unique');
                $table->unique(['booking_outbound_id'], 'travel_round_trips_outbound_unique');
                $table->unique(['booking_return_id'], 'travel_round_trips_return_unique');
                $table->index(['company_id', 'booking_outbound_id'], 'travel_round_trips_company_outbound_idx');
            });

            DB::statement("COMMENT ON TABLE travel_round_trips IS 'Aller-retour combiné — lien entre deux réservations (TRAVEL-802/#6093).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_round_trips');
    }
};
