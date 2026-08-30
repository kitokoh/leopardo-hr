<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6208 (RESTO-603) - RestaurantManager : politique d'annulation par branche.
 *
 * Ajoute `cancel_free_hours` (délai de grâce en heures, annulation gratuite
 * au-delà) et `cancel_fee_bps` (pénalité en points de base appliquée sur le
 * dépôt si l'annulation tombe dans le délai) à `restaurant_branches`.
 * Additive et réentrante : garde `schemaTableExists` + `hasColumn` (règle #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_branches')) {
            return;
        }

        Schema::table('restaurant_branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_branches', 'cancel_free_hours')) {
                $table->unsignedInteger('cancel_free_hours')->nullable()->after('currency');
            }

            if (! Schema::hasColumn('restaurant_branches', 'cancel_fee_bps')) {
                $table->unsignedInteger('cancel_fee_bps')->nullable()->after('cancel_free_hours');
            }
        });

        DB::statement("COMMENT ON COLUMN restaurant_branches.cancel_free_hours IS 'Delai de grace d annulation (heures) - RESTO-603/#6208.';");
        DB::statement("COMMENT ON COLUMN restaurant_branches.cancel_fee_bps IS 'Penalite d annulation en points de base - RESTO-603/#6208.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_branches')) {
            return;
        }

        Schema::table('restaurant_branches', function (Blueprint $table): void {
            $table->dropColumn(['cancel_free_hours', 'cancel_fee_bps']);
        });
    }
};
