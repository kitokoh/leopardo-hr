<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6014 (TRAVEL-201) — travel_countries : référentiel des pays, tenant-scoped.
 *
 * Référentiel ISO 3166-1 seedé au provisioning (TravelGeoSeederService) ;
 * chaque tenant possède son propre jeu (company_id non nullable), modifiable
 * via l'API (statut actif/désactivé).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_countries')) {
            // Issue #7452 — la table est créée par 2026_08_29_000900_6014_create_travel_countries_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_countries', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_countries', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_countries', 'iso2')) {
                    $table->char('iso2', 2);
                }
                if (! schemaHasColumn('travel_countries', 'iso3')) {
                    $table->char('iso3', 3);
                }
                if (! schemaHasColumn('travel_countries', 'name')) {
                    $table->string('name', 120);
                }
                if (! schemaHasColumn('travel_countries', 'phone_code')) {
                    $table->unsignedSmallInteger('phone_code')->nullable();
                }
                if (! schemaHasColumn('travel_countries', 'status')) {
                    $table->string('status', 20)->default('active');
                }
                if (! schemaHasColumn('travel_countries', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_countries', 'company_id')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_countries', 'iso2')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('iso2');
            });
        }
        if (schemaHasColumn('travel_countries', 'iso3')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('iso3');
            });
        }
        if (schemaHasColumn('travel_countries', 'name')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_countries', 'phone_code')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('phone_code');
            });
        }
        if (schemaHasColumn('travel_countries', 'status')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_countries', 'created_at')) {
            Schema::table('travel_countries', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
