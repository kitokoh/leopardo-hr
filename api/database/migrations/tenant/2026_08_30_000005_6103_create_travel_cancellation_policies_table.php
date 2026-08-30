<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6103 (TRAVEL-813) — Politiques d'annulation configurables.
 *
 * Règle appliquée à l'annulation/remboursement d'une réservation :
 *   - ciblage : par trajet, par classe, ou globale au tenant (null = tout) ;
 *   - `cancel_before_hours` : pénalité applicable si l'annulation a lieu
 *     moins de N heures avant le départ (null = toujours) ;
 *   - `penalty_percent` : pénalité calculée serveur (0..100) ;
 *   - `refundable` : détermine si un remboursement est possible.
 *
 * Résolution : (trip, class) → (trip, null) → (null, class) → défaut
 * (null, null), la règle la plus spécifique gagne ; une seule règle par
 * combinaison (contrainte unique).
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
                $table->unsignedInteger('cancel_before_hours')->nullable();
                $table->unsignedTinyInteger('penalty_percent')->default(0);
                $table->boolean('refundable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->string('description', 255)->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'trip_id', 'class_id'],
                    'travel_cancel_policies_company_trip_class_unique',
                );
                $table->index(
                    ['company_id', 'is_active'],
                    'travel_cancel_policies_company_active_idx',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_cancellation_policies');
    }
};
