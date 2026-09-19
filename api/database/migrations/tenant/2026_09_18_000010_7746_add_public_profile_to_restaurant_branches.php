<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #7746 (RESTO-901) - RestaurantManager : profil public par branche.
 *
 * Colonnes additives sur `restaurant_branches` (opt-in, defaut prive) :
 * - `is_public` : exposition dans l'annuaire public (defaut false) ;
 * - `public_slug` : slug public UNIQUE GLOBAL (cross-tenant, nullable) ;
 * - `establishment_type` : type d'etablissement (enum applicative
 *   RestaurantEstablishmentType : restaurant, fast_food, pizzeria, brasserie,
 *   cafe, patisserie, traiteur, autre) ;
 * - `cuisine_types` : liste JSON de cuisines proposees ;
 * - `public_description`, `cover_image_url` : contenu editorial public ;
 * - `latitude`/`longitude` : geolocalisation (decimal 10,7) pour la recherche
 *   par proximite (haversine SQL) ;
 * - index (is_public, latitude, longitude) pour l'annuaire public.
 *
 * Additive et reentrante : garde `schemaTableExists` + `hasColumn`
 * (regle #5431), `down()` complet. Jamais de second Schema::create (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('restaurant_branches')) {
            return;
        }

        Schema::table('restaurant_branches', function (Blueprint $table): void {
            $isNew = ! Schema::hasColumn('restaurant_branches', 'is_public');

            if ($isNew) {
                $table->boolean('is_public')->default(false);
            }

            if (! Schema::hasColumn('restaurant_branches', 'public_slug')) {
                $table->string('public_slug', 160)->nullable()
                    ->unique('restaurant_branches_public_slug_unique');
            }

            if (! Schema::hasColumn('restaurant_branches', 'establishment_type')) {
                $table->string('establishment_type', 30)->nullable();
            }

            if (! Schema::hasColumn('restaurant_branches', 'cuisine_types')) {
                $table->json('cuisine_types')->nullable();
            }

            if (! Schema::hasColumn('restaurant_branches', 'public_description')) {
                $table->text('public_description')->nullable();
            }

            if (! Schema::hasColumn('restaurant_branches', 'cover_image_url')) {
                $table->string('cover_image_url', 500)->nullable();
            }

            if (! Schema::hasColumn('restaurant_branches', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('restaurant_branches', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if ($isNew) {
                // Index de l'annuaire public (filtre is_public + fenetre geo).
                $table->index(
                    ['is_public', 'latitude', 'longitude'],
                    'restaurant_branches_public_geo_idx'
                );
            }
        });

        DB::statement("COMMENT ON COLUMN restaurant_branches.is_public IS 'Opt-in annuaire public des restaurants - RESTO-901/#7746.';");
        DB::statement("COMMENT ON COLUMN restaurant_branches.public_slug IS 'Slug public unique GLOBAL (cross-tenant) du profil de branche - RESTO-901/#7746.';");
        DB::statement("COMMENT ON COLUMN restaurant_branches.establishment_type IS 'Type d etablissement (RestaurantEstablishmentType) - RESTO-901/#7746.';");
        DB::statement("COMMENT ON COLUMN restaurant_branches.cuisine_types IS 'Cuisines proposees (liste JSON) - RESTO-901/#7746.';");
    }

    public function down(): void
    {
        if (! schemaTableExists('restaurant_branches')) {
            return;
        }

        Schema::table('restaurant_branches', function (Blueprint $table): void {
            if (Schema::hasColumn('restaurant_branches', 'is_public')) {
                $table->dropIndex('restaurant_branches_public_geo_idx');
            }

            if (Schema::hasColumn('restaurant_branches', 'public_slug')) {
                $table->dropUnique('restaurant_branches_public_slug_unique');
            }

            $table->dropColumn([
                'is_public',
                'public_slug',
                'establishment_type',
                'cuisine_types',
                'public_description',
                'cover_image_url',
                'latitude',
                'longitude',
            ]);
        });
    }
};
