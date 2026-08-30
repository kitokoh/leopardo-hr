<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6023 (TRAVEL-210) — travel_tickets + travel_payments.
 *
 * Billets nominatifs (spec §5.3) — `validation_code` stocke un hash (jamais
 * le code en clair, vérifié côté check-in) ; `travel_payments` — un
 * paiement référence une seule réservation du tenant, `callback_payload_redacted`
 * ne contient jamais de secret/token (webhooks provider, pattern Accounting/
 * Billing HMAC).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_tickets')) {
            Schema::create('travel_tickets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('ticket_number', 40);
                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('passenger_id');
                $table->string('validation_code', 64);
                $table->unsignedBigInteger('pdf_asset_id')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('valid_from')->nullable();
                $table->timestamp('valid_until')->nullable();
                $table->string('status', 20)->default('issued');
                $table->timestamp('checked_in_at')->nullable();
                $table->unsignedBigInteger('checked_in_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'ticket_number'], 'travel_tickets_company_number_unique');
                $table->index(['company_id', 'booking_id'], 'travel_tickets_company_booking_idx');
                $table->index(['company_id', 'passenger_id'], 'travel_tickets_company_passenger_idx');
            });

            DB::statement("ALTER TABLE travel_tickets ADD CONSTRAINT travel_tickets_status_check CHECK (status IN ('issued', 'checked_in', 'void'))");
            DB::statement("COMMENT ON TABLE travel_tickets IS 'Billets nominatifs — validation_code hash uniquement (TRAVEL-210/#6023).'");
        }

        if (! schemaTableExists('travel_payments')) {
            Schema::create('travel_payments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);
                $table->unsignedBigInteger('booking_id');
                $table->string('provider_code', 20);
                $table->unsignedInteger('amount_minor');
                $table->char('currency', 3);
                $table->string('status', 20)->default('pending');
                $table->string('provider_reference', 120)->nullable();
                $table->jsonb('callback_payload_redacted')->nullable();
                $table->string('idempotency_key', 255);

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'travel_payments_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'travel_payments_company_idempotency_unique');
                $table->index(['company_id', 'booking_id'], 'travel_payments_company_booking_idx');
            });

            DB::statement("ALTER TABLE travel_payments ADD CONSTRAINT travel_payments_provider_check CHECK (provider_code IN ('cash', 'pvit', 'momo', 'card'))");
            DB::statement("ALTER TABLE travel_payments ADD CONSTRAINT travel_payments_status_check CHECK (status IN ('pending', 'confirmed', 'failed', 'refunded'))");
            DB::statement('ALTER TABLE travel_payments ADD CONSTRAINT travel_payments_amount_positive_check CHECK (amount_minor > 0)');
            DB::statement("COMMENT ON TABLE travel_payments IS 'Paiements dune reservation — callback_payload_redacted sans secret/token (TRAVEL-210/#6023).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_payments');
        Schema::dropIfExists('travel_tickets');
    }
};
