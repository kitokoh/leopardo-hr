<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6211 (RESTO-606) - RestaurantManager : opt-in RGPD fidélité.
 *
 * Ajoute `opted_in_at` (timestamp nullable) à `restaurant_loyalty_customers` :
 * l'activation du programme de fidélité exige l'opt-in du client (RGPD).
 * Additive et réentrante (garde `hasColumn`, règle #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_loyalty_customers')) {
            return;
        }

        Schema::table('restaurant_loyalty_customers', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_loyalty_customers', 'opted_in_at')) {
                $table->timestamp('opted_in_at')->nullable()->after('tier_code');
            }
        });

        DB::statement("COMMENT ON COLUMN restaurant_loyalty_customers.opted_in_at IS 'Horodatage opt-in RGPD du programme de fidelite - RESTO-606/#6211.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_loyalty_customers')) {
            return;
        }

        Schema::table('restaurant_loyalty_customers', function (Blueprint $table): void {
            $table->dropColumn('opted_in_at');
        });
    }
};
