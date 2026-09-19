<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7746 (RESTO-901) - RestaurantManager : publication en ligne des plats.
 *
 * `is_published_online` (bool, defaut false, opt-in) sur `restaurant_products` :
 * seul un produit publie ET disponible (`is_available`) apparait dans le menu
 * du profil public d'une branche. Index pour le filtre du menu public.
 *
 * Additive et reentrante : garde `schemaTableExists` + `hasColumn`
 * (regle #5431), `down()` complet. Jamais de second Schema::create (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_products')) {
            return;
        }

        Schema::table('restaurant_products', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_products', 'is_published_online')) {
                $table->boolean('is_published_online')->default(false)
                    ->index('restaurant_products_published_online_idx');
            }
        });

        DB::statement("COMMENT ON COLUMN restaurant_products.is_published_online IS 'Publication du produit sur le menu public de la branche (opt-in) - RESTO-901/#7746.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_products')) {
            return;
        }

        Schema::table('restaurant_products', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_products', 'is_published_online')) {
                $table->dropIndex('restaurant_products_published_online_idx');
                $table->dropColumn('is_published_online');
            }
        });
    }
};
