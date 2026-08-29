<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6026 (TRAVEL-213) — travel_rental_bookings.
 *
 * Réservations de location (spec §5.4). La contrainte applicative de
 * non-chevauchement des dates pour un même véhicule est portée par l'Action
 * du lot 3xx (TRAVEL-320) — cette migration expose uniquement l'index
 * `(company_id, vehicle_id, start_date)` nécessaire à sa requête de
 * détection, et le modèle fournit un scope `overlapping()` réutilisable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_rental_bookings')) {
            return;
        }

        Schema::create('travel_rental_bookings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id')->index();

            $table->string('reference', 40);
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('customer_contact_id')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('total_amount_minor');
            $table->char('currency', 3);
            $table->unsignedInteger('deposit_amount_minor')->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 255);

            $table->timestamps();

            $table->unique(['company_id', 'reference'], 'travel_rental_bookings_company_reference_unique');
            $table->unique(['company_id', 'idempotency_key'], 'travel_rental_bookings_company_idempotency_unique');
            $table->index(['company_id', 'vehicle_id', 'start_date'], 'travel_rental_bookings_company_vehicle_start_idx');
        });

        DB::statement("ALTER TABLE travel_rental_bookings ADD CONSTRAINT travel_rental_bookings_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled', 'completed'))");
        DB::statement("ALTER TABLE travel_rental_bookings ADD CONSTRAINT travel_rental_bookings_payment_status_check CHECK (payment_status IN ('pending', 'confirmed', 'failed', 'refunded'))");
        DB::statement('ALTER TABLE travel_rental_bookings ADD CONSTRAINT travel_rental_bookings_dates_check CHECK (end_date >= start_date)');
        DB::statement("COMMENT ON TABLE travel_rental_bookings IS 'Reservations de location — non-chevauchement applique par lAction 3xx (TRAVEL-213/#6026).'");
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_rental_bookings');
    }
};
