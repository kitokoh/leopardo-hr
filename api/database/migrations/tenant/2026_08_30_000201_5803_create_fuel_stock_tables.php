<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5803 — Stocks, cuves et rapprochement FuelStation (FUEL-009, BC-15 FUEL).
 *
 * `fuel_stock_movements` : entrées (livraisons), sorties (ventes), ouvertures,
 * ajustements et clôtures par cuve — écrits SEULS par FuelStockService, avec
 * clé d'idempotence (jobs de rapprochement rejouables sans doublon).
 * `fuel_stock_reconciliations` : rapport d'écart par station/période —
 * explicable (écarts jamais ajustés silencieusement), rejouable (updateOrCreate
 * par (company_id, station_id, period)).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_stock_movements')) {
            Schema::create('fuel_stock_movements', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedBigInteger('tank_id')->index();
                // delivery | sale | adjustment | opening | closing
                $table->string('type', 20);
                $table->decimal('quantity', 14, 3);
                $table->decimal('unit_price', 14, 2)->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->string('idempotency_key', 191);
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_stock_movements_company_key_unique');
                $table->index(['company_id', 'tank_id', 'occurred_at'], 'fuel_stock_movements_company_tank_time_idx');
                $table->index(['company_id', 'type', 'occurred_at'], 'fuel_stock_movements_company_type_time_idx');

                $table->foreign(['tank_id', 'company_id'], 'fuel_stock_movements_tank_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_tanks')
                    ->onDelete('cascade');
            });

            DB::statement("COMMENT ON TABLE fuel_stock_movements IS 'Mouvements de stock par cuve : livraisons, ventes, ajustements — idempotents (FUEL-009 #5803).'");
        }

        if (! schemaTableExists('fuel_stock_reconciliations')) {
            Schema::create('fuel_stock_reconciliations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->string('period', 7); // YYYY-MM
                $table->decimal('opening_quantity', 14, 3)->default(0);
                $table->decimal('delivered_quantity', 14, 3)->default(0);
                $table->decimal('sold_quantity', 14, 3)->default(0);
                $table->decimal('expected_level', 14, 3)->default(0);
                $table->decimal('actual_level', 14, 3)->nullable();
                $table->decimal('variance_liters', 14, 3)->default(0);
                $table->string('status', 20)->default('insufficient_data'); // ok|variance|insufficient_data
                $table->jsonb('data')->nullable();
                $table->unsignedInteger('reconciled_by')->nullable();
                $table->timestampTz('reconciled_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'station_id', 'period'], 'fuel_stock_recon_company_station_period_unique');
                $table->index(['company_id', 'station_id', 'period'], 'fuel_stock_recon_company_station_period_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_stock_reconciliations IS 'Rapports de rapprochement stock par station/période — explicables, jamais ajustés silencieusement (FUEL-009 #5803).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_stock_reconciliations');
        Schema::dropIfExists('fuel_stock_movements');
    }
};
