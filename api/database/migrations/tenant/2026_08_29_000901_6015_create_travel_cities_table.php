<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6015 (TRAVEL-202) — travel_cities : villes du référentiel, tenant-scoped.
 *
 * Rattachement au pays par code ISO2 (pas de FK inter-tenant). Unicité
 * (company_id, country_iso2, name) pour garantir l'idempotence du seed
 * (insertOrIgnore). `region` = découpage de premier niveau ; découpages à
 * 3 niveaux planifiés en Phase 2 (spec §13).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_cities')) {
            // Issue #7452 — la table est créée par 2026_08_29_000002_6015_create_travel_cities_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_cities', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_cities', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_cities', 'country_iso2')) {
                    $table->char('country_iso2', 2);
                }
                if (! schemaHasColumn('travel_cities', 'name')) {
                    $table->string('name', 120);
                }
                if (! schemaHasColumn('travel_cities', 'region')) {
                    $table->string('region', 120)->nullable();
                }
                if (! schemaHasColumn('travel_cities', 'latitude')) {
                    $table->double('latitude')->nullable();
                }
                if (! schemaHasColumn('travel_cities', 'longitude')) {
                    $table->double('longitude')->nullable();
                }
                if (! schemaHasColumn('travel_cities', 'status')) {
                    $table->string('status', 20)->default('active');
                }
                if (! schemaHasColumn('travel_cities', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_cities', 'company_id')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_cities', 'country_iso2')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('country_iso2');
            });
        }
        if (schemaHasColumn('travel_cities', 'name')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_cities', 'region')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('region');
            });
        }
        if (schemaHasColumn('travel_cities', 'latitude')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('latitude');
            });
        }
        if (schemaHasColumn('travel_cities', 'longitude')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('longitude');
            });
        }
        if (schemaHasColumn('travel_cities', 'status')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_cities', 'created_at')) {
            Schema::table('travel_cities', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
