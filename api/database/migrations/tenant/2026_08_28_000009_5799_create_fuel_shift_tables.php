<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUEL-005 (#5799) — Shifts et affectations pompistes.
 *
 * `fuel_shifts` : créneau de travail récurrent d'une station (nom, horaires
 * début/fin, statut) — `station_id` (uuid, nullable) référencera la table
 * `fuel_stations` livrée par FUEL-002 (FK ajoutée par cette migration dès
 * que la table existe, sinon colonne indexée seule — voir garde idempotente).
 *
 * `fuel_shift_assignments` : affectation d'un employé à un shift pour une
 * date donnée. Chevauchement contrôlé au niveau application
 * (`FuelShiftService::assertNoOverlap`) : un employé ne peut être affecté à
 * deux shifts dont les horaires se recouvrent le même jour.
 *
 * Migration additive + idempotente (garde #1962/#5431), réf. issue dans le
 * nom, exécutée par `php artisan leopardo:migrate` (schémas tenant).
 *
 * Rollback : `down()` supprime les deux tables (FK interne fuel_shift
 * d'abord).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_shifts')) {
            Schema::create('fuel_shifts', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                // Référence station (uuid) — résolue par FUEL-002 ; FK posée
                // plus bas uniquement si la table `fuel_stations` existe déjà.
                $table->uuid('station_id')->nullable()->index();
                $table->string('name', 120);
                $table->time('start_time');
                $table->time('end_time');
                $table->string('status', 20)->default('active'); // active|inactive
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'fuel_shifts_company_name_unique');
            });
        }

        if (! schemaTableExists('fuel_shift_assignments')) {
            Schema::create('fuel_shift_assignments', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->uuid('shift_id')->index();
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
            });
        }

        // FK internes (même schéma tenant) — garde information_schema pour
        // rester idempotent (PostgreSQL : ADD CONSTRAINT IF NOT EXISTS n'existe pas).
        $this->addForeignKeyIfMissing(
            'fuel_shift_assignments',
            'fuel_shift_assignments_shift_fk',
            'shift_id',
            'fuel_shifts',
            'id'
        );
        $this->addForeignKeyIfMissing(
            'fuel_shift_assignments',
            'fuel_shift_assignments_employee_fk',
            'employee_id',
            'employees',
            'id'
        );

        // Référence station : FK posée uniquement si la table FUEL-002 existe
        // déjà (sinon simple colonne indexée — la FK sera ajoutée par FUEL-002).
        $this->addForeignKeyIfMissing(
            'fuel_shifts',
            'fuel_shifts_station_fk',
            'station_id',
            'fuel_stations',
            'id'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_shift_assignments');
        Schema::dropIfExists('fuel_shifts');
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        string $column,
        string $references,
        string $referencedColumn,
    ): void {
        if (! schemaTableExists($references)) {
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
                 FOREIGN KEY ({$column}) REFERENCES {$references} ({$referencedColumn}) ON DELETE CASCADE"
            );
        }
    }
};
