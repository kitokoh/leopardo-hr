<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5804 (FUEL-010) — incidents, maintenance et tâches.
 *
 * Trois tables tenant-scoped :
 *  - `fuel_incidents` : incident d'équipement (pompe/cuve/compteur/autre)
 *    d'une station — priorité, statut, assignation, résolution. Workflow
 *    AUDITÉ (chaque transition écrit une ligne `audit_logs`, catégorie
 *    fuel_incident) ; notification par événements (FUEL-019) sans
 *    exposition PII.
 *  - `fuel_maintenance_tasks` : maintenance préventive/corrective, liée ou
 *    non à un incident, avec échéance et statut.
 *  - `fuel_incident_attachments` : pièces jointes contrôlées (mime/size
 *    validés au niveau Request — aucune exécution, stockage interne).
 *
 * FKs composites anti cross-tenant (pattern FUEL-002/003) : impossible de
 * rattacher un incident/tâche à la station d'un AUTRE tenant. `assigned_to`
 * référence un employé (contrôle tenant au niveau service). `reported_by`
 * garde l'auteur sans FK (un employé archivé ne casse pas l'historique).
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431),
 * clés primaires bigint, company_id uuid indexé, CHECKs gardés pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_incidents')) {
            Schema::create('fuel_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id');
                // pump | tank | meter | other
                $table->string('equipment_type', 20)->default('other');
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('title', 160);
                $table->text('description');
                // low | medium | high | critical
                $table->string('priority', 20)->default('medium');
                // reported | assigned | in_progress | resolved | closed
                $table->string('status', 20)->default('reported');
                $table->unsignedInteger('reported_by')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->timestampTz('closed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_incidents_company_status_idx');
                $table->index(['company_id', 'station_id', 'priority'], 'fuel_incidents_company_station_priority_idx');
                $table->index(['company_id', 'assigned_to'], 'fuel_incidents_company_assignee_idx');

                $table->unique(['id', 'company_id'], 'fuel_incidents_id_company_unique');

                $table->foreign(['station_id', 'company_id'], 'fuel_incidents_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('fuel_incidents');

            if ($schema !== null && ! $this->constraintExists('fuel_incidents_equipment_type_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_incidents ADD CONSTRAINT fuel_incidents_equipment_type_check CHECK (equipment_type IN ('pump', 'tank', 'meter', 'other'))"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_incidents_priority_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_incidents ADD CONSTRAINT fuel_incidents_priority_check CHECK (priority IN ('low', 'medium', 'high', 'critical'))"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_incidents_status_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_incidents ADD CONSTRAINT fuel_incidents_status_check CHECK (status IN ('reported', 'assigned', 'in_progress', 'resolved', 'closed'))"
                );
            }
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('incident_id')->nullable();
                $table->string('title', 160);
                // preventive | corrective
                $table->string('task_type', 20)->default('preventive');
                $table->timestampTz('scheduled_at')->nullable();
                // planned | in_progress | completed | cancelled
                $table->string('status', 20)->default('planned');
                $table->unsignedInteger('assigned_to')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_maint_tasks_company_status_idx');
                $table->index(['company_id', 'scheduled_at'], 'fuel_maint_tasks_company_scheduled_idx');

                $table->foreign(['incident_id', 'company_id'], 'fuel_maint_tasks_incident_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_incidents')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('fuel_maintenance_tasks');

            if ($schema !== null && ! $this->constraintExists('fuel_maint_tasks_type_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_maintenance_tasks ADD CONSTRAINT fuel_maint_tasks_type_check CHECK (task_type IN ('preventive', 'corrective'))"
                );
            }

            if ($schema !== null && ! $this->constraintExists('fuel_maint_tasks_status_check')) {
                DB::statement(
                    "ALTER TABLE {$schema}.fuel_maintenance_tasks ADD CONSTRAINT fuel_maint_tasks_status_check CHECK (status IN ('planned', 'in_progress', 'completed', 'cancelled'))"
                );
            }
        }

        if (! schemaTableExists('fuel_incident_attachments')) {
            Schema::create('fuel_incident_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('incident_id');
                $table->string('filename', 255);
                $table->string('storage_path', 500);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'incident_id'], 'fuel_incident_attachments_company_incident_idx');

                $table->foreign(['incident_id', 'company_id'], 'fuel_attachments_incident_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_incidents')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_incident_attachments');
        Schema::dropIfExists('fuel_maintenance_tasks');
        Schema::dropIfExists('fuel_incidents');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
