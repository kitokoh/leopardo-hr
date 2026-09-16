<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        if (schemaTableExists('travel_cancellation_policies')) {
            // Issue #7452 — la table est créée par 2026_08_30_001504_6103_create_travel_cancellation_policies_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_cancellation_policies', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'trip_id')) {
                    $table->unsignedBigInteger('trip_id')->nullable();
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'class_id')) {
                    $table->unsignedBigInteger('class_id')->nullable();
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'hours_before_departure')) {
                    $table->unsignedInteger('hours_before_departure')->default(0);
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'penalty_percent')) {
                    $table->unsignedTinyInteger('penalty_percent')->default(0);
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'refundable')) {
                    $table->boolean('refundable')->default(true);
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'created_by_user_id')) {
                    $table->unsignedBigInteger('created_by_user_id')->nullable();
                }
                if (! schemaHasColumn('travel_cancellation_policies', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_cancellation_policies', 'company_id')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'trip_id')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('trip_id');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'class_id')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('class_id');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'hours_before_departure')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('hours_before_departure');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'penalty_percent')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('penalty_percent');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'refundable')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('refundable');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'created_by_user_id')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('created_by_user_id');
            });
        }
        if (schemaHasColumn('travel_cancellation_policies', 'created_at')) {
            Schema::table('travel_cancellation_policies', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
