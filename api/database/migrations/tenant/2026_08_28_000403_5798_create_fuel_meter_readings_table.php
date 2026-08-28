<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module FuelStation — Issue #5798 (FUEL-004).
 *
 * Relevés de compteur cumulés : station/pompe/compteur/pompiste/shift,
 * heure UTC + locale, delta, rollover, anomalie, correction versionnée.
 *
 * Règles :
 *   - company_id uuid NON nullable ; isolation BelongsToCompany ;
 *   - idempotence : UNIQUE (company_id, meter_id, read_at) — zéro doublon ;
 *   - valeur jamais négative (CHECK) ; delta calculé par le service ;
 *   - anomalie : valeur décroissante (sauf rollover explicite) ;
 *   - correction versionnée : une ligne de correction liée via
 *     `corrects_reading_id` (append-only, jamais de UPDATE) + audit
 *     (trait Auditable) ;
 *   - FK composite (meter_id, company_id) → fuel_meters, CONDITIONNELLE à
 *     l'existence de la table (FUEL-003 #5797) : ordre de merge #5797 → #5798.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_meter_readings')) {
            Schema::create('fuel_meter_readings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('meter_id');
                $table->unsignedBigInteger('pump_id')->nullable();
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedBigInteger('station_id')->nullable();
                $table->unsignedBigInteger('operator_id')->nullable();
                $table->string('shift_ref', 60)->nullable();
                $table->decimal('reading_value', 16, 3);
                $table->timestamp('read_at');          // UTC
                $table->string('read_at_local', 40)->nullable(); // horodatage local du site
                $table->decimal('delta', 16, 3)->nullable();
                $table->boolean('is_rollover')->default(false);
                $table->boolean('is_anomaly')->default(false);
                $table->string('anomaly_reason', 60)->nullable();
                $table->unsignedBigInteger('corrects_reading_id')->nullable();
                $table->text('metadata')->nullable(); // chiffré
                $table->timestamps();

                // Idempotence : zéro doublon (même compteur, même instant).
                $table->unique(['company_id', 'meter_id', 'read_at'], 'fuel_meter_readings_dup_unique');
                $table->index(['company_id', 'meter_id', 'read_at'], 'fuel_meter_readings_company_meter_time_idx');
                $table->index(['company_id', 'read_at'], 'fuel_meter_readings_company_time_idx');
                $table->index(['company_id', 'operator_id'], 'fuel_meter_readings_company_operator_idx');
                $table->index(['company_id', 'is_anomaly'], 'fuel_meter_readings_company_anomaly_idx');
            });

            DB::statement(
                'ALTER TABLE fuel_meter_readings ADD CONSTRAINT fuel_meter_readings_value_check CHECK (reading_value >= 0)'
            );
            DB::statement(
                'ALTER TABLE fuel_meter_readings ADD CONSTRAINT fuel_meter_readings_anomaly_reason_check '
                ."CHECK (anomaly_reason IS NULL OR anomaly_reason IN ('decreasing_value','meter_replaced','out_of_range'))"
            );
        }

        if (schemaTableExists('fuel_meters')) {
            $row = DB::selectOne(
                "SELECT 1 FROM information_schema.table_constraints
                  WHERE table_name = 'fuel_meter_readings' AND constraint_name = 'fuel_meter_readings_meter_company_fk' LIMIT 1"
            );
            if ($row === null) {
                Schema::table('fuel_meter_readings', function (Blueprint $table): void {
                    $table->foreign(['meter_id', 'company_id'], 'fuel_meter_readings_meter_company_fk')
                        ->references(['id', 'company_id'])->on('fuel_meters')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_meter_readings');
    }
};
