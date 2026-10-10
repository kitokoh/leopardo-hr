<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8358 (VTC-02) — BC-34 VTC : table tenant `vtc_rides` (agrégat racine).
 *
 * Courses VTC/taxi : référence `VTC-YYYY-NNNNNN` unique par tenant, passager
 * (compte nullable OU invité nom/téléphone), points de prise en charge et de
 * dépose en décimal WGS 84 (dérivation v1 — voir 000102), estimation
 * persistée (distance, durée, prix en minor units) et prix final, statut du
 * cycle de vie (state machine VTC-04), horodatages de cycle, motif
 * d'annulation tracé, **`idempotency_key` unique par tenant** (zéro doublon
 * de création, pattern BC-26). Tenant-first, aucune FK, réentrante +
 * down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('vtc_rides')) {
            Schema::create('vtc_rides', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 40);

                // Passager : compte rattaché OU invité (nom/téléphone).
                $table->unsignedBigInteger('passenger_user_id')->nullable();
                $table->string('passenger_name', 120)->nullable();
                $table->string('passenger_phone', 40)->nullable();

                $table->decimal('pickup_latitude', 10, 7);
                $table->decimal('pickup_longitude', 10, 7);
                $table->text('pickup_address')->nullable();
                $table->decimal('dropoff_latitude', 10, 7);
                $table->decimal('dropoff_longitude', 10, 7);
                $table->text('dropoff_address')->nullable();

                $table->string('status', 20)->default('requested');

                $table->unsignedBigInteger('fare_profile_id')->nullable();
                $table->unsignedInteger('estimated_distance_m')->nullable();
                $table->unsignedInteger('estimated_duration_s')->nullable();
                $table->unsignedInteger('estimated_price_minor')->nullable();
                $table->unsignedInteger('final_price_minor')->nullable();
                $table->string('currency', 3);

                $table->unsignedBigInteger('driver_id')->nullable();

                $table->timestamp('requested_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('arrived_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('expired_at')->nullable();
                $table->string('cancel_reason', 200)->nullable();

                $table->uuid('idempotency_key')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'reference'], 'vtc_rides_company_reference_unique');
                $table->unique(['company_id', 'idempotency_key'], 'vtc_rides_company_idempotency_unique');
                $table->index(['company_id', 'status', 'created_at'], 'vtc_rides_company_status_date_idx');
                $table->index(['company_id', 'driver_id'], 'vtc_rides_company_driver_idx');
                $table->index(['company_id', 'passenger_user_id'], 'vtc_rides_company_passenger_idx');
            });

            DB::statement("COMMENT ON TABLE vtc_rides IS 'Courses VTC/taxi - reference unique par tenant, idempotency_key unique, estimation persistee en minor units, cycle de vie horodate (VTC-02/#8358).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vtc_rides');
    }
};
