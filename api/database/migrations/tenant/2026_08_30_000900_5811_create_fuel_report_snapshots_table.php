<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5811 (FUEL-017) — read models de reporting FuelStation.
 *
 * `fuel_report_snapshots` : instantanés agrégés par (station, type,
 * période) — volumes par pompe, ventes, shifts, écarts, stock, performance
 * station. Dashboards SANS jointures profondes (le payload est pré-agrégé),
 * recalcul IDEMPOTENT (un seul snapshot par clé — rejouer remplace, jamais
 * de doublon), p95 documenté côté API (temps de génération borné).
 *
 * L'export asynchrone (CSV, URL signée) est porté par FUEL-018 ; ce
 * snapshot est la source des deux.
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * FK composite anti cross-tenant (pattern FUEL-002/003).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_report_snapshots')) {
            Schema::create('fuel_report_snapshots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();

                // pump_volumes | sales | shifts | variances | stock | station_performance
                $table->string('snapshot_type', 40);
                $table->date('period_start');
                $table->date('period_end');
                $table->jsonb('payload');
                $table->unsignedInteger('generated_by')->nullable();
                $table->timestampTz('generated_at')->useCurrent();
                $table->timestampTz('created_at')->useCurrent();
                $table->timestampTz('updated_at')->useCurrent();

                // Idempotence : une clé (station, type, période) = un snapshot.
                $table->unique(
                    ['company_id', 'station_id', 'snapshot_type', 'period_start', 'period_end'],
                    'fuel_report_snapshots_unique'
                );
                $table->index(['company_id', 'station_id', 'snapshot_type'], 'fuel_report_snapshots_type_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_report_snapshots_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_report_snapshots IS 'Read models de reporting FuelStation (pré-agrégés, recalcul idempotent) — FUEL-017 (#5811).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_report_snapshots');
    }
};
