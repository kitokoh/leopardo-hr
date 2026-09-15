<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6108 (TRAVEL-905) — Annonces : référentiels types + positions.
 *
 * `travel_advert_types` (nature d'annonce) et `travel_advert_positions`
 * (emplacements de publication) — tenant-scoped, code unique par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_advert_types')) {
            // Issue #7452 — la table est créée par 2026_08_30_000017_6110_create_travel_advert_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_advert_types', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_advert_types', 'code')) {
                    $table->string('code', 40)->nullable();
                }
                if (! schemaHasColumn('travel_advert_types', 'label')) {
                    $table->string('label', 120)->nullable();
                }
                if (! schemaHasColumn('travel_advert_types', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_advert_types', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }

        if (schemaTableExists('travel_advert_positions')) {
            // Issue #7452 — la table est créée par 2026_08_30_000017_6110_create_travel_advert_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_advert_positions', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_advert_positions', 'code')) {
                    $table->string('code', 40)->nullable();
                }
                if (! schemaHasColumn('travel_advert_positions', 'label')) {
                    $table->string('label', 120)->nullable();
                }
                if (! schemaHasColumn('travel_advert_positions', 'created_at')) {
                    $table->timestampTz('created_at')->useCurrent();
                }
                if (! schemaHasColumn('travel_advert_positions', 'updated_at')) {
                    $table->timestampTz('updated_at')->useCurrent();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_advert_positions', 'company_id')) {
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_advert_positions', 'code')) {
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                $table->dropColumn('code');
            });
        }
        if (schemaHasColumn('travel_advert_positions', 'label')) {
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                $table->dropColumn('label');
            });
        }
        if (schemaHasColumn('travel_advert_positions', 'created_at')) {
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_advert_positions', 'updated_at')) {
            Schema::table('travel_advert_positions', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
        if (schemaHasColumn('travel_advert_types', 'company_id')) {
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_advert_types', 'code')) {
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                $table->dropColumn('code');
            });
        }
        if (schemaHasColumn('travel_advert_types', 'label')) {
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                $table->dropColumn('label');
            });
        }
        if (schemaHasColumn('travel_advert_types', 'created_at')) {
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_advert_types', 'updated_at')) {
            Schema::table('travel_advert_types', function (Blueprint $table): void {
                $table->dropColumn('updated_at');
            });
        }
    }
};
