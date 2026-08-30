<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6022 (TRAVEL-209) — travel_bookings + travel_passengers.
 *
 * Réservations multi-passagers (spec §5.3). `idempotency_key` unique par
 * tenant : une requête rejouée (retry réseau, double clic guichet) ne crée
 * jamais deux réservations. `document_number_encrypted`/`document_number_hash`
 * : le n° de pièce d'identité n'est jamais stocké en clair (RGPD, §V de la
 * Constitution) — le hash permet une recherche exacte sans déchiffrer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_bookings')) {
            Schema::create('travel_bookings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('trip_id');
                $table->string('status', 20)->default('pending');
                $table->unsignedInteger('passenger_count');
                $table->unsignedInteger('total_amount_minor');
                $table->char('currency', 3);
                $table->string('booking_source', 20)->default('office');
                $table->unsignedBigInteger('customer_contact_id')->nullable();
                $table->unsignedBigInteger('booked_by_user_id')->nullable();
                $table->string('payment_status', 20)->default('pending');
                $table->timestamp('expires_at')->nullable();
                $table->string('idempotency_key', 255);
                $table->unsignedInteger('version')->default(1);

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'travel_bookings_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'travel_bookings_company_idempotency_unique');
                $table->index(['company_id', 'trip_id'], 'travel_bookings_company_trip_idx');
                $table->index(['company_id', 'status'], 'travel_bookings_company_status_idx');
            });

            DB::statement("ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_status_check CHECK (status IN ('pending', 'confirmed', 'cancelled', 'refunded', 'completed'))");
            DB::statement("ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_source_check CHECK (booking_source IN ('online', 'office', 'phone', 'partner'))");
            DB::statement("ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_payment_status_check CHECK (payment_status IN ('pending', 'confirmed', 'failed', 'refunded'))");
            DB::statement('ALTER TABLE travel_bookings ADD CONSTRAINT travel_bookings_passenger_count_check CHECK (passenger_count > 0)');
            DB::statement("COMMENT ON TABLE travel_bookings IS 'Reservations multi-passagers — idempotency_key unique par tenant (TRAVEL-209/#6022).'");
        }

        if (! schemaTableExists('travel_passengers')) {
            Schema::create('travel_passengers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('booking_id');
                $table->string('full_name', 160);
                $table->date('birth_date')->nullable();
                $table->string('document_type', 20)->nullable();
                $table->text('document_number_encrypted')->nullable();
                $table->string('document_number_hash', 64)->nullable();
                $table->string('age_category', 20)->default('adult');
                $table->unsignedBigInteger('class_id');
                $table->unsignedInteger('seat_number')->nullable();
                $table->unsignedInteger('unit_price_minor');

                $table->timestamps();

                $table->index(['company_id', 'booking_id'], 'travel_passengers_company_booking_idx');
                $table->index(['company_id', 'document_number_hash'], 'travel_passengers_company_doc_hash_idx');
            });

            DB::statement("ALTER TABLE travel_passengers ADD CONSTRAINT travel_passengers_document_type_check CHECK (document_type IS NULL OR document_type IN ('national_id', 'passport', 'birth_certificate', 'other'))");
            DB::statement("ALTER TABLE travel_passengers ADD CONSTRAINT travel_passengers_age_category_check CHECK (age_category IN ('infant', 'child', 'adult'))");
            DB::statement("COMMENT ON TABLE travel_passengers IS 'Passagers dune reservation — n de piece chiffre + hash, jamais en clair (TRAVEL-209/#6022).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_passengers');
        Schema::dropIfExists('travel_bookings');
    }
};
