<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5804 (FUEL-010) — incidents, maintenance et tâches FuelStation.
 *
 * `fuel_incidents` : incident équipement signalé par un pompiste ou un
 * manager (catégorie, sévérité, description REDACTED — jamais de PII ni de
 * secrets), workflow audité (reported → assigned → resolved → closed),
 * pièces jointes contrôlées côté application (MIME allowlist, taille —
 * seules les métadonnées sont stockées ici, pas les fichiers).
 *
 * `fuel_maintenance_tasks` : maintenance préventive ou corrective dérivée
 * d'un incident (ou autonome) : priorité, échéance, assignation,
 * résolution. Une tâche peut référencer l'incident qui l'a déclenchée.
 *
 * Toutes les données sont tenant-scoped (`company_id` non nullable) ; FKs
 * composites (x, company_id) anti cross-tenant (pattern FUEL-002/003).
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        // FUEL-010 : la FK composite (incident_id, company_id) exige la
        // contrainte UNIQUE (id, company_id) sur fuel_incidents.
        if (schemaTableExists('fuel_incidents') && ! $this->uniqueExists('fuel_incidents', 'fuel_incidents_id_company_unique')) {
            $schema = resolveTableSchema('fuel_incidents');
            if ($schema !== null) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_incidents ADD CONSTRAINT fuel_incidents_id_company_unique UNIQUE (id, company_id)"
                );
            }
        }

        if (! schemaTableExists('fuel_incidents')) {
            Schema::create('fuel_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();

                // equipment | fuel_leak | safety | cash | other
                $table->string('category', 40)->default('other');
                // low | medium | high | critical
                $table->string('severity', 20)->default('medium');
                $table->text('description_redacted');
                // reported | assigned | resolved | closed
                $table->string('status', 20)->default('reported');
                $table->unsignedInteger('reported_by')->index();
                $table->timestampTz('reported_at')->useCurrent();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->timestampTz('assigned_at')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->timestampTz('closed_at')->nullable();
                // Métadonnées des pièces jointes (nom, taille, mime) — jamais le contenu.
                $table->jsonb('attachments_metadata')->nullable();
                $table->string('external_id', 120)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_incidents_ext_unique');
                $table->unique(['id', 'company_id'], 'fuel_incidents_id_company_unique');
                $table->index(['company_id', 'status', 'reported_at'], 'fuel_incidents_status_reported_idx');
                $table->index(['company_id', 'assigned_to', 'status'], 'fuel_incidents_assigned_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_incidents_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('reported_by', 'fuel_incidents_reported_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_incidents IS 'Incidents équipement FuelStation (workflow audité, description redacted, PJ contrôlées) — FUEL-010 (#5804).'");
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedBigInteger('incident_id')->nullable();

                $table->string('title', 200);
                $table->text('description_redacted')->nullable();
                // preventive | corrective | inspection
                $table->string('task_type', 20)->default('corrective');
                // low | medium | high | critical
                $table->string('priority', 20)->default('medium');
                // open | in_progress | done | cancelled
                $table->string('status', 20)->default('open');
                $table->unsignedInteger('assigned_to')->nullable();
                $table->timestampTz('due_at')->nullable();
                $table->timestampTz('started_at')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->string('external_id', 120)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'external_id'], 'fuel_maintenance_tasks_ext_unique');
                $table->index(['company_id', 'status', 'due_at'], 'fuel_tasks_status_due_idx');
                $table->index(['company_id', 'assigned_to', 'status'], 'fuel_tasks_assigned_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_tasks_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['incident_id', 'company_id'], 'fuel_tasks_incident_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_incidents')
                    ->cascadeOnDelete();
            });

            DB::statement("COMMENT ON TABLE fuel_maintenance_tasks IS 'Tâches de maintenance FuelStation (préventive/corrective, priorisée, workflow audité) — FUEL-010 (#5804).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_maintenance_tasks');
        Schema::dropIfExists('fuel_incidents');
    }

    private function uniqueExists(string $table, string $constraint): bool
    {
        $schema = resolveTableSchema($table);
        if ($schema === null) {
            return true;
        }

        return DB::selectOne(
            'SELECT 1
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE c.conname = ?
                AND n.nspname = ?
              LIMIT 1',
            [$constraint, $schema]
        ) !== null;
    }
};
