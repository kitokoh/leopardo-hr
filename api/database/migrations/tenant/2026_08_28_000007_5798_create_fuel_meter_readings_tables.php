<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #5798 — FuelStation : relevés de compteur.
 *
 * `fuel_meter_readings` : relevés cumulés par compteur (station, site,
 * pompe, compteur, opérateur, shift, heure UTC + locale), avec delta
 * calculé, détection de décroissance (`anomaly`), rollover et idempotence
 * par unicité `(company_id, meter_id, reading_at)`.
 * `fuel_meter_reading_corrections` : corrections versionnées et auditées
 * (ancienne/nouvelle valeur, motif, acteur).
 *
 * Conventions : uuid PK, `company_id` non nullable indexé, index tenant-first
 * `(company_id, meter_id, reading_at)`, garde schemaTableExists() (#1613).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_meter_readings')) {
            Schema::create('fuel_meter_readings', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('meter_id')->index();
                $table->uuid('pump_id')->nullable()->index();
                $table->uuid('site_id')->nullable()->index();
                $table->uuid('station_id')->nullable()->index();
                $table->uuid('operator_id')->nullable()->index();  // employee (pompiste)
                $table->uuid('shift_id')->nullable()->index();
                $table->decimal('reading_value', 16, 3);
                $table->timestampTz('reading_at');                 // UTC
                $table->string('reading_at_local', 40)->nullable();
                $table->decimal('delta', 16, 3)->nullable();       // vs relevé précédent
                $table->boolean('rollover')->default(false);
                $table->boolean('anomaly')->default(false);
                $table->string('source', 20)->default('manual');   // manual|api|device
                $table->string('note', 255)->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'meter_id', 'reading_at'], 'fuel_readings_company_meter_at_unique');
                $table->index(['company_id', 'meter_id', 'reading_at'], 'fuel_readings_company_meter_at_index');
                $table->index(['company_id', 'anomaly'], 'fuel_readings_company_anomaly_index');
            });
        }

        if (! schemaTableExists('fuel_meter_reading_corrections')) {
            Schema::create('fuel_meter_reading_corrections', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('reading_id')->index();
                $table->decimal('old_value', 16, 3);
                $table->decimal('new_value', 16, 3);
                $table->string('reason', 255);
                $table->uuid('corrected_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'reading_id'], 'fuel_corrections_company_reading_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_meter_reading_corrections');
        Schema::dropIfExists('fuel_meter_readings');
    }
};
