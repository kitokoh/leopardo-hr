<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5799 (FUEL-005) — shifts et affectations pompistes.
 *
 * `fuel_shifts` : créneau de travail récurrent d'une station (nom, horaires
 * début/fin, statut). `station_id` BIGINT nullable avec FK COMPOSITE
 * (station_id, company_id) → fuel_stations(id, company_id) : impossible de
 * rattacher un shift à la station d'un AUTRE tenant (pattern FUEL-002/003).
 *
 * `fuel_shift_assignments` : affectation d'un employé à un shift pour une
 * date donnée. Chevauchement contrôlé au niveau application
 * (`FuelShiftService::assertNoOverlap`) : un employé ne peut être affecté à
 * deux shifts dont les horaires se recouvrent le même jour.
 *
 * Intégration FUEL-004 : `fuel_meter_readings.shift_id` (livrée par #5798
 * sans FK — la table n'existait pas encore) est reliée à `fuel_shifts`
 * (FK gardée, ON DELETE SET NULL).
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * clés primaires bigint ($table->id()), company_id uuid indexé, CHECKs
 * gardés pg_constraint. Rollback : `down()` retire la FK des relevés puis
 * supprime les deux tables (FK interne fuel_shift d'abord).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_shifts')) {
            Schema::create('fuel_shifts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                $table->string('name', 120);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('status', 20)->default('active'); // active|inactive
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'fuel_shifts_company_name_unique');

                // FK composite anti cross-tenant (pattern FUEL-002/003).
                $table->foreign(['station_id', 'company_id'], 'fuel_shifts_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_shift_assignments')) {
            Schema::create('fuel_shift_assignments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('shift_id')->index();
                $table->unsignedInteger('employee_id')->index();
                $table->date('assignment_date');
                // scheduled|confirmed|completed|cancelled
                $table->string('status', 20)->default('scheduled');
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'shift_id', 'employee_id', 'assignment_date'],
                    'fuel_shift_assignments_unique'
                );

                $table->foreign('shift_id', 'fuel_shift_assignments_shift_fk')
                    ->references('id')
                    ->on('fuel_shifts')
                    ->cascadeOnDelete();
                $table->foreign('employee_id', 'fuel_shift_assignments_employee_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });
        }

        // Intégration FUEL-004 : relier les relevés de compteur au shift
        // (colonne nullable livrée par #5798 — FK posée ici, gardée).
        $this->addForeignKeyIfMissing(
            'fuel_meter_readings',
            'fuel_readings_shift_fk',
            ['shift_id'],
            'fuel_shifts',
            ['id'],
            'SET NULL'
        );

        $this->addChecks();
    }

    public function down(): void
    {
        $this->dropConstraintIfExists('fuel_meter_readings', 'fuel_readings_shift_fk');

        Schema::dropIfExists('fuel_shift_assignments');
        Schema::dropIfExists('fuel_shifts');
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        array $columns,
        string $references,
        array $referencedColumns,
        string $onDelete = 'CASCADE',
    ): void {
        if (! schemaTableExists($table) || ! schemaTableExists($references)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_schema = ANY (current_schemas(false))',
            [$constraint]
        );

        if ($exists === null) {
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
                 FOREIGN KEY (".implode(', ', $columns).')
                 REFERENCES '.$references.' ('.implode(', ', $referencedColumns).") ON DELETE {$onDelete}"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function dropConstraintIfExists(string $table, string $constraint): void
    {
        if (! schemaTableExists($table) || ! $this->constraintExists($constraint)) {
            return;
        }

        $schema = resolveTableSchema($table);

        if ($schema !== null) {
            DB::statement("ALTER TABLE {$schema}.{$table} DROP CONSTRAINT {$constraint}");
        }
    }

    private function addChecks(): void
    {
        foreach ([
            'fuel_shifts' => [
                'fuel_shifts_status_check' => "status IN ('active', 'inactive')",
            ],
            'fuel_shift_assignments' => [
                'fuel_shift_assignments_status_check' => "status IN ('scheduled', 'confirmed', 'completed', 'cancelled')",
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
