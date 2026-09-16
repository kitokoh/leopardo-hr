<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6016 (TRAVEL-203) — travel_stations + travel_offices.
 *
 * Gares/terminaux (départ & arrivée des trajets) et bureaux de vente
 * (guichets). Les deux référencent une ville du référentiel tenant-scoped ;
 * code unique de gare par tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_stations')) {
            // Issue #7452 — la table est créée par 2026_08_29_000003_6016_create_travel_stations_and_offices_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_stations', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_stations', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_stations', 'code')) {
                    $table->string('code', 40);
                }
                if (! schemaHasColumn('travel_stations', 'name')) {
                    $table->string('name', 120);
                }
                if (! schemaHasColumn('travel_stations', 'city_id')) {
                    $table->unsignedBigInteger('city_id');
                }
                if (! schemaHasColumn('travel_stations', 'address')) {
                    $table->string('address', 255)->nullable();
                }
                if (! schemaHasColumn('travel_stations', 'contact_phone')) {
                    $table->string('contact_phone', 40)->nullable();
                }
                if (! schemaHasColumn('travel_stations', 'timezone')) {
                    $table->string('timezone', 50)->default('UTC');
                }
                if (! schemaHasColumn('travel_stations', 'is_terminal')) {
                    $table->boolean('is_terminal')->default(false);
                }
                if (! schemaHasColumn('travel_stations', 'status')) {
                    $table->string('status', 20)->default('active');
                }
                if (! schemaHasColumn('travel_stations', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_offices')) {
            // Issue #7452 — la table est créée par 2026_08_29_000003_6016_create_travel_stations_and_offices_tables.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_offices', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_offices', 'company_id')) {
                    $table->uuid('company_id')->index();
                }
                if (! schemaHasColumn('travel_offices', 'name')) {
                    $table->string('name', 120);
                }
                if (! schemaHasColumn('travel_offices', 'city_id')) {
                    $table->unsignedBigInteger('city_id');
                }
                if (! schemaHasColumn('travel_offices', 'address')) {
                    $table->string('address', 255)->nullable();
                }
                if (! schemaHasColumn('travel_offices', 'contact_phone')) {
                    $table->string('contact_phone', 40)->nullable();
                }
                if (! schemaHasColumn('travel_offices', 'status')) {
                    $table->string('status', 20)->default('active');
                }
                if (! schemaHasColumn('travel_offices', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_offices', 'company_id')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_offices', 'name')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_offices', 'city_id')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('city_id');
            });
        }
        if (schemaHasColumn('travel_offices', 'address')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('address');
            });
        }
        if (schemaHasColumn('travel_offices', 'contact_phone')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('contact_phone');
            });
        }
        if (schemaHasColumn('travel_offices', 'status')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_offices', 'created_at')) {
            Schema::table('travel_offices', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_stations', 'company_id')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_stations', 'code')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('code');
            });
        }
        if (schemaHasColumn('travel_stations', 'name')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('name');
            });
        }
        if (schemaHasColumn('travel_stations', 'city_id')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('city_id');
            });
        }
        if (schemaHasColumn('travel_stations', 'address')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('address');
            });
        }
        if (schemaHasColumn('travel_stations', 'contact_phone')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('contact_phone');
            });
        }
        if (schemaHasColumn('travel_stations', 'timezone')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('timezone');
            });
        }
        if (schemaHasColumn('travel_stations', 'is_terminal')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('is_terminal');
            });
        }
        if (schemaHasColumn('travel_stations', 'status')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_stations', 'created_at')) {
            Schema::table('travel_stations', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
