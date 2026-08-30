<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5804 (FUEL-010) — incidents équipements, maintenance et tâches.
 *
 * Deux tables additives, tenant-scoped (FK composites anti cross-tenant) :
 *  - `fuel_incidents` : cycle audité open → assigned → in_progress →
 *    resolved → closed (transitions validées en application, jamais en base),
 *    sévérité allowlistée, `occurred_at` horodaté, signalé par un employé du
 *    tenant, résolution tracée (resolved_by/resolution_notes/resolved_at) ;
 *  - `fuel_maintenance_tasks` : préventive/corrective, priorité, affectation,
 *    statut pending → in_progress → completed|cancelled, rattachable à un
 *    incident (FK interne).
 *
 * Migration additive + idempotente (garde schemaTableExists #1962/#5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_incidents')) {
            Schema::create('fuel_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                // pump|tank|meter|other
                $table->string('equipment_type', 20)->default('other');
                $table->unsignedBigInteger('equipment_id')->nullable();
                // low|medium|high|critical
                $table->string('severity', 20)->default('medium');
                // open|assigned|in_progress|resolved|closed
                $table->string('status', 20)->default('open');
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->unsignedInteger('reported_by');
                $table->unsignedInteger('assigned_to')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->timestampTz('closed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'station_id', 'status'], 'fuel_incidents_company_station_status_idx');
                $table->index(['company_id', 'severity'], 'fuel_incidents_company_severity_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_incidents_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('reported_by', 'fuel_incidents_reported_by_fk')
                    ->references('id')
                    ->on('employees');
                $table->foreign('assigned_to', 'fuel_incidents_assigned_to_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
                $table->foreign('resolved_by', 'fuel_incidents_resolved_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->index();
                $table->unsignedBigInteger('incident_id')->nullable()->index();
                // preventive|corrective
                $table->string('type', 20)->default('preventive');
                // low|medium|high
                $table->string('priority', 20)->default('medium');
                // pending|in_progress|completed|cancelled
                $table->string('status', 20)->default('pending');
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->date('scheduled_for')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'station_id', 'status'], 'fuel_maintenance_tasks_company_station_status_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_maintenance_tasks_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign('incident_id', 'fuel_maintenance_tasks_incident_fk')
                    ->references('id')
                    ->on('fuel_incidents')
                    ->nullOnDelete();
                $table->foreign('assigned_to', 'fuel_maintenance_tasks_assigned_to_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
                $table->foreign('completed_by', 'fuel_maintenance_tasks_completed_by_fk')
                    ->references('id')
                    ->on('employees')
                    ->nullOnDelete();
            });
        }

        $this->addChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_maintenance_tasks');
        Schema::dropIfExists('fuel_incidents');
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('fuel_incidents');

        if ($schema !== null) {
            $checks = [
                'fuel_incidents_severity_check' => "severity IN ('low', 'medium', 'high', 'critical')",
                'fuel_incidents_status_check' => "status IN ('open', 'assigned', 'in_progress', 'resolved', 'closed')",
                'fuel_incidents_equipment_type_check' => "equipment_type IN ('pump', 'tank', 'meter', 'other')",
            ];

            foreach ($checks as $name => $check) {
                if (! $this->constraintExists($name)) {
                    DB::statement("ALTER TABLE {$schema}.fuel_incidents ADD CONSTRAINT {$name} CHECK ({$check})");
                }
            }
        }

        $schemaTasks = resolveTableSchema('fuel_maintenance_tasks');

        if ($schemaTasks !== null) {
            $checks = [
                'fuel_maintenance_tasks_type_check' => "type IN ('preventive', 'corrective')",
                'fuel_maintenance_tasks_priority_check' => "priority IN ('low', 'medium', 'high')",
                'fuel_maintenance_tasks_status_check' => "status IN ('pending', 'in_progress', 'completed', 'cancelled')",
            ];

            foreach ($checks as $name => $check) {
                if (! $this->constraintExists($name)) {
                    DB::statement("ALTER TABLE {$schemaTasks}.fuel_maintenance_tasks ADD CONSTRAINT {$name} CHECK ({$check})");
                }
            }
        }
    }
};
