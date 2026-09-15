<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6076 (TRAVEL-506) — Read models des rapports TravelAgency.
 *
 * Deux tables de lecture recalculables par des jobs idempotents :
 * - `travel_daily_sales` : ventes journalières agrégées (source, statut,
 *   passagers, montants en minor units) — la reprise du job donne un état
 *   identique (upsert par clé naturelle, pas d'accumulation).
 * - `travel_trip_occupancy` : occupation par trajet (sièges vendus/total,
 *   taux) — mêmes garanties.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('travel_daily_sales')) {
            // Issue #7452 — la table est créée par 2026_08_30_000915_6076_create_travel_report_read_models_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_daily_sales', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'sale_date')) {
                    $table->date('sale_date')->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'source')) {
                    $table->string('source', 20)->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'status')) {
                    $table->string('status', 20)->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'booking_count')) {
                    $table->unsignedInteger('booking_count')->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'passenger_count')) {
                    $table->unsignedInteger('passenger_count')->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'amount_minor')) {
                    $table->unsignedBigInteger('amount_minor')->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'currency')) {
                    $table->char('currency', 3)->nullable();
                }
                if (! schemaHasColumn('travel_daily_sales', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (schemaTableExists('travel_trip_occupancy')) {
            // Issue #7452 — la table est créée par 2026_08_30_000915_6076_create_travel_report_read_models_table.php ; cette
            // génération ne rattrape que les colonnes qui lui manquent.
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                if (! schemaHasColumn('travel_trip_occupancy', 'company_id')) {
                    $table->uuid('company_id')->index()->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'trip_id')) {
                    $table->unsignedBigInteger('trip_id')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'departure_date')) {
                    $table->date('departure_date')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'total_seats')) {
                    $table->unsignedInteger('total_seats')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'sold_seats')) {
                    $table->unsignedInteger('sold_seats')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'reserved_seats')) {
                    $table->unsignedInteger('reserved_seats')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'free_seats')) {
                    $table->unsignedInteger('free_seats')->nullable();
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'occupancy_rate')) {
                    $table->decimal('occupancy_rate', 5, 4)->default(0);
                }
                if (! schemaHasColumn('travel_trip_occupancy', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('travel_trip_occupancy', 'company_id')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'trip_id')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('trip_id');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'departure_date')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('departure_date');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'total_seats')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('total_seats');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'sold_seats')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('sold_seats');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'reserved_seats')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('reserved_seats');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'free_seats')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('free_seats');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'occupancy_rate')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('occupancy_rate');
            });
        }
        if (schemaHasColumn('travel_trip_occupancy', 'created_at')) {
            Schema::table('travel_trip_occupancy', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'company_id')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('company_id');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'sale_date')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('sale_date');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'source')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('source');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'status')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('status');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'booking_count')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('booking_count');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'passenger_count')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('passenger_count');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'amount_minor')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('amount_minor');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'currency')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('currency');
            });
        }
        if (schemaHasColumn('travel_daily_sales', 'created_at')) {
            Schema::table('travel_daily_sales', function (Blueprint $table): void {
                $table->dropColumn('created_at');
            });
        }
    }
};
