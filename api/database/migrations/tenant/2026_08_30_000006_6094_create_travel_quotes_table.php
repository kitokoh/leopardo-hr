<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6094 (TRAVEL-803) — travel_quotes : devis & réservations de groupe.
 *
 * Devis B2B (≥ MIN_GROUP_SIZE passagers) validé côté serveur (tarifs du
 * trajet, unités mineures), plafond figé à la réservation groupée. La
 * facturation différée passe par l'événement outbox `travel.quote.booked.v1`
 * (contrat Accounting, jamais d'import direct — spec D7).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_quotes')) {
            Schema::create('travel_quotes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('trip_id');
                $table->string('status', 20)->default('draft');
                $table->unsignedBigInteger('customer_contact_id')->nullable();
                $table->unsignedInteger('passenger_count');
                $table->jsonb('passengers_json')->nullable();
                $table->unsignedInteger('total_amount_minor');
                $table->char('currency', 3);
                $table->timestamp('expires_at')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->string('idempotency_key', 255);
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'travel_quotes_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'travel_quotes_company_idempotency_unique');
                $table->index(['company_id', 'trip_id'], 'travel_quotes_company_trip_idx');
            });

            DB::statement("ALTER TABLE travel_quotes ADD CONSTRAINT travel_quotes_status_check CHECK (status IN ('draft', 'confirmed', 'booked', 'cancelled', 'expired'))");
            DB::statement("COMMENT ON TABLE travel_quotes IS 'Devis de groupe — total figé serveur, plafond à la réservation (TRAVEL-803/#6094).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_quotes');
    }
};
