<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6109 (TRAVEL-906) — Annonces : grille tarifaire.
 *
 * `travel_advert_prices` : prix par image et par caractère en unités
 * mineures, devise (cohérence devise tenant), une grille par
 * (type, position). Contrainte CHECK : montants strictement positifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Issue #7452 — la table est créée par
        // `2026_08_30_000017_6110_create_travel_advert_tables.php` ; cette génération
        // ne rattrape que les colonnes qui lui manquent. Le code écrit
        // `advert_type_id`/`advert_position_id` alors que la 1re génération déclare
        // `type_id`/`position_id` : les deux jeux coexistent (rattrapage additif).
        if (schemaTableExists('travel_advert_prices')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_advert_prices', 'advert_type_id')) {
                    $table->unsignedBigInteger('advert_type_id')->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'advert_position_id')) {
                    $table->unsignedBigInteger('advert_position_id')->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_advert_prices', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_advert_prices', 'company_id')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'advert_type_id')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('advert_type_id');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'advert_position_id')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('advert_position_id');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'price_per_image_minor')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('price_per_image_minor');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'price_per_character_minor')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('price_per_character_minor');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'currency')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'created_at')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'updated_at')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
