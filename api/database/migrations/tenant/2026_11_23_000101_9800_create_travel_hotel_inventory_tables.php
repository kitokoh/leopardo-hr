<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M4 (évaluation leopardo-hr) — inventaire hôtelier PAR DATE + réservations.
 *
 * Le catalogue `travel_hotels` / `travel_hotel_rooms` (TRAVEL-214/#6027) ne
 * portait aucune notion de disponibilité : une chambre existait ou non, sans
 * dimension temporelle. Impossible donc de vendre une nuit, ni d'empêcher la
 * surréservation. Ce lot ajoute :
 *
 * - `travel_hotel_room_inventories` : une ligne par (chambre, date).
 *   `rooms_total` = capacité vendable ce soir-là (0 = chambre fermée pour
 *   maintenance), `rooms_booked` = déjà vendu. Invariant porté par une
 *   contrainte CHECK : `0 <= rooms_booked <= rooms_total`.
 * - `travel_hotel_bookings` : la réservation elle-même, avec sa clé
 *   d'idempotence (un rejeu réseau ne crée jamais deux réservations).
 *
 * Additive et réentrante (gardes `schemaTableExists`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_hotel_room_inventories')) {
            Schema::create('travel_hotel_room_inventories', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('room_id');
                $table->date('stay_date');
                $table->unsignedInteger('rooms_total')->default(1);
                $table->unsignedInteger('rooms_booked')->default(0);
                $table->string('status', 20)->default('active');

                $table->timestamps();

                $table->unique(['company_id', 'room_id', 'stay_date'], 'travel_hotel_inventory_company_room_date_unique');
                $table->index(['company_id', 'stay_date'], 'travel_hotel_inventory_company_date_idx');
            });

            DB::statement('ALTER TABLE travel_hotel_room_inventories ADD CONSTRAINT travel_hotel_inventory_booked_check CHECK (rooms_booked <= rooms_total)');
            DB::statement("COMMENT ON TABLE travel_hotel_room_inventories IS 'Inventaire hotelier par chambre et par date - controle de surreservation (M4).'");
        }

        if (! schemaTableExists('travel_hotel_bookings')) {
            Schema::create('travel_hotel_bookings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('room_id');
                $table->date('check_in');
                $table->date('check_out');
                $table->unsignedInteger('rooms')->default(1);
                $table->unsignedInteger('nights');
                $table->unsignedInteger('total_amount_minor');
                $table->char('currency', 3);
                $table->string('status', 20)->default('pending');
                $table->string('idempotency_key', 255);

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'travel_hotel_bookings_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'travel_hotel_bookings_company_idempotency_unique');
                $table->index(['company_id', 'room_id', 'check_in'], 'travel_hotel_bookings_company_room_checkin_idx');
            });

            DB::statement('ALTER TABLE travel_hotel_bookings ADD CONSTRAINT travel_hotel_bookings_dates_check CHECK (check_out > check_in)');
            DB::statement('ALTER TABLE travel_hotel_bookings ADD CONSTRAINT travel_hotel_bookings_rooms_check CHECK (rooms > 0)');
            DB::statement("COMMENT ON TABLE travel_hotel_bookings IS 'Reservations hotelieres par date - idempotentes (M4).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_hotel_bookings');
        Schema::dropIfExists('travel_hotel_room_inventories');
    }
};
