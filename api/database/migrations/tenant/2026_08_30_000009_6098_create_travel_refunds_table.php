<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6098 (TRAVEL-808) — travel_refunds : remboursements (partiels inclus).
 *
 * Un remboursement cible une réservation (booking_id) et optionnellement un
 * passager (passenger_id NULL = remboursement complet). `refund_key` unique
 * par tenant → rejeu sans double remboursement (acceptance TRAVEL-808).
 * `penalty_minor` = pénalité appliquée (règles par classe/élasticité, jamais
 * calculée côté client).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_refunds')) {
            Schema::create('travel_refunds', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('booking_id');
                $table->unsignedBigInteger('passenger_id')->nullable();
                $table->unsignedInteger('amount_minor');
                $table->unsignedInteger('penalty_minor')->default(0);
                $table->char('currency', 3);
                $table->string('reason', 500);
                $table->string('refund_key', 255);
                $table->unsignedBigInteger('refunded_by_user_id')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'refund_key'], 'travel_refunds_company_key_unique');
                $table->index(['company_id', 'booking_id'], 'travel_refunds_company_booking_idx');
            });

            DB::statement("COMMENT ON TABLE travel_refunds IS 'Remboursements — partiels par passager, idempotents (TRAVEL-808/#6098).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_refunds');
    }
};
