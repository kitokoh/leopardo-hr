<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6103 (TRAVEL-813) — travel_cancellation_policies : politique d'annulation
 * configurable par trajet/classe.
 *
 * Spécificité décroissante (trajet+classe > classe > trajet > défaut tenant) ;
 * `hours_before_departure` = seuil avant le départ sous lequel la pénalité
 * s'applique ; `refundable=false` = non remboursable (pénalité 100 %).
 * Appliquée dans Cancel/Refund via TravelRefundPolicyResolver (TRAVEL-808).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_cancellation_policies')) {
            Schema::create('travel_cancellation_policies', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->unsignedBigInteger('trip_id')->nullable();
                $table->unsignedBigInteger('class_id')->nullable();
                $table->unsignedInteger('hours_before_departure')->default(0);
                $table->unsignedTinyInteger('penalty_percent')->default(0);
                $table->boolean('refundable')->default(true);
                $table->unsignedBigInteger('created_by_user_id')->nullable();

                $table->timestamps();

                $table->index(['company_id', 'trip_id'], 'travel_cancellation_policies_company_trip_idx');
                $table->index(['company_id', 'class_id'], 'travel_cancellation_policies_company_class_idx');
            });

            DB::statement('ALTER TABLE travel_cancellation_policies ADD CONSTRAINT travel_cancellation_policies_penalty_check CHECK (penalty_percent BETWEEN 0 AND 100)');
            DB::statement("COMMENT ON TABLE travel_cancellation_policies IS 'Politiques d''annulation configurables (TRAVEL-813/#6103).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_cancellation_policies');
    }
};
