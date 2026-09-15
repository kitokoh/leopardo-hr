<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6112 (TRAVEL-909) — Sites touristiques (annuaire legacy gv-back).
 *
 * `travel_tourist_sites` : nom, description redigée, ville (référentiel
 * tenant-scoped), coordonnées, image, statut. Recherche par ville.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Issue #7452 — la table est créée par
        // `2026_08_30_000018_6112_create_travel_tourist_sites_table.php` ; cette
        // génération ne rattrape que `image_asset_id` (les autres colonnes existent).
        if (schemaTableExists('travel_tourist_sites')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_tourist_sites', 'image_asset_id')) {
                    $table->unsignedBigInteger('image_asset_id')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_tourist_sites', 'company_id')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'name')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'description_redacted')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('description_redacted');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'city_id')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('city_id');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'latitude')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('latitude');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'longitude')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('longitude');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'image_asset_id')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('image_asset_id');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'status')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'created_at')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_tourist_sites', 'updated_at')) {
            Schema::table('travel_tourist_sites', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
