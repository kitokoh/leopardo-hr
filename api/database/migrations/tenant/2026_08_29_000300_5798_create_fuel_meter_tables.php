<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5798 (FUEL-004) — relevés de compteur par pompe, heure et
 * opérateur (spec §13).
 *
 * fuel_meter_readings : relevés cumulés, append-only. Une correction ne
 * supprime jamais le relevé original : elle crée une nouvelle version
 * (source_code='correction') et marque l'original 'corrected'.
 *
 * fuel_meter_intervals : delta entre deux relevés consécutifs d'un même
 * compteur. Une valeur décroissante sans rollover documenté → statut
 * 'anomaly' (revue obligatoire), jamais de delta négatif silencieux.
 *
 * Idempotence : UNIQUE(company_id, idempotency_key) — un rejeu réseau
 * renvoie le résultat existant, aucun doublon.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_meter_readings')) {
            Schema::create('fuel_meter_readings', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');
                $table->unsignedBigInteger('pump_id');
                $table->unsignedBigInteger('meter_id');

                // Valeur cumulée en unités mineures entières (jamais de flottant métier).
                $table->unsignedBigInteger('reading_value_minor');
                $table->string('reading_unit', 20)->default('l');

                $table->timestamp('captured_at_utc');
                $table->timestamp('captured_at_station_local');
                $table->string('timezone', 64)->default('UTC');

                $table->unsignedBigInteger('captured_by_employee_id')->nullable();
                $table->unsignedBigInteger('shift_id')->nullable();

                // operator | import | device | correction
                $table->string('source_code', 20)->default('operator');
                $table->string('device_reference', 120)->nullable();

                $table->string('idempotency_key', 191);

                // submitted | accepted | rejected | corrected
                $table->string('status', 20)->default('submitted');
                $table->text('correction_reason')->nullable();

                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_readings_company_key_unique');
                $table->index(['company_id', 'meter_id', 'captured_at_utc'], 'fuel_readings_company_meter_time_idx');
                $table->index(['company_id', 'pump_id', 'captured_at_utc'], 'fuel_readings_company_pump_time_idx');
                $table->index(['company_id', 'status'], 'fuel_readings_company_status_idx');

                $table->foreign(['meter_id', 'company_id'], 'fuel_readings_meter_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_meter_registers')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_meter_intervals')) {
            Schema::create('fuel_meter_intervals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('meter_id');

                $table->unsignedBigInteger('previous_reading_id');
                $table->unsignedBigInteger('current_reading_id');

                $table->unsignedBigInteger('previous_value_minor');
                $table->unsignedBigInteger('current_value_minor');
                $table->bigInteger('delta_minor'); // peut être négatif (anomalie)

                $table->unsignedBigInteger('interval_seconds')->default(0);
                $table->timestamp('calculated_at')->useCurrent();

                // valid | rollover | anomaly | pending_review
                $table->string('calculation_status', 20)->default('valid');

                $table->timestamps();

                $table->unique(['company_id', 'previous_reading_id', 'current_reading_id'], 'fuel_intervals_pair_unique');
                $table->index(['company_id', 'meter_id', 'calculated_at'], 'fuel_intervals_company_meter_time_idx');

                $table->foreign(['meter_id', 'company_id'], 'fuel_intervals_meter_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_meter_registers')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_meter_intervals');
        Schema::dropIfExists('fuel_meter_readings');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        foreach ([
            'fuel_meter_readings' => [
                'fuel_readings_source_check' => "source_code IN ('operator', 'import', 'device', 'correction')",
                'fuel_readings_status_check' => "status IN ('submitted', 'accepted', 'rejected', 'corrected')",
            ],
            'fuel_meter_intervals' => [
                'fuel_intervals_status_check' => "calculation_status IN ('valid', 'rollover', 'anomaly', 'pending_review')",
            ],
        ] as $table => $constraints) {
            $schema = resolveTableSchema($table);

            if ($schema === null) {
                continue;
            }

            foreach ($constraints as $name => $check) {
                if ($this->constraintExists($name)) {
                    continue;
                }

                DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$check})");
            }
        }
    }
};
