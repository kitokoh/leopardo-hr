<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRAVEL-906 (#6109) — Grille tarifaire des annonces payantes (spec §3).
 *
 * Prix en unités mineures (minor units) : prix de l'image et prix par
 * caractère, pour un couple (type, position) dans la devise du tenant.
 * Unicité (company_id, advert_type_id, advert_position_id, currency) ;
 * bornes non négatives (CHECK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_advert_prices')) {
            // Issue #7452 — la table est créée par 2026_08_30_000017_6110_create_travel_advert_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_advert_prices', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'advert_type_id')) {
                    $table->unsignedBigInteger('advert_type_id')->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'advert_position_id')) {
                    $table->unsignedBigInteger('advert_position_id')->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'price_image_minor')) {
                    $table->unsignedBigInteger('price_image_minor')->default(0);
                }
                if (! schemaHasColumn('travel_advert_prices', 'price_character_minor')) {
                    $table->unsignedBigInteger('price_character_minor')->default(0);
                }
                if (! schemaHasColumn('travel_advert_prices', 'currency')) {
                    $table->char('currency', 3)->nullable();
                }
                if (! schemaHasColumn('travel_advert_prices', 'created_at')) {
                    $table->timestamps();
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
        if (schemaHasColumn('travel_advert_prices', 'price_image_minor')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('price_image_minor');
            });
        }
        if (schemaHasColumn('travel_advert_prices', 'price_character_minor')) {
            Schema::table('travel_advert_prices', function (Blueprint $table): void {
                $table->dropColumn('price_character_minor');
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
    }
};
