<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5804 (FUEL-010) — incidents, maintenance et tâches.
 *
 * - `fuel_incidents` : signalement d'incident équipement (pompe/cuve/
 *   compteur/autre) avec sévérité et workflow audité
 *   reported → assigned → in_progress → resolved → closed.
 * - `fuel_maintenance_tasks` : maintenance préventive/corrective, liée
 *   optionnellement à un incident, avec priorité et échéance.
 * - `fuel_incident_attachments` : pièces jointes contrôlées (mime +
 *   taille validés à l'application ; chemin serveur, jamais de chemin
 *   client).
 *
 * Toutes les tables sont tenant-scoped avec FKs composites (x, company_id)
 * → fuel_stations/fuel_incidents : aucun rattachement cross-tenant.
 * `idempotency_key` unique par tenant sur les incidents (rejeu sûr).
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

                $table->string('equipment_type', 20); // pump|tank|meter|other
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('severity', 12); // low|medium|high|critical
                $table->string('status', 16)->default('reported'); // reported|assigned|in_progress|resolved|closed
                $table->string('title', 160);
                $table->text('description');
                $table->unsignedInteger('reported_by')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->dateTime('resolved_at')->nullable();
                $table->string('idempotency_key', 64);
                $table->timestamps();

                $table->unique(['company_id', 'idempotency_key'], 'fuel_incidents_idem_key_unique');
                $table->index(['company_id', 'status'], 'fuel_incidents_company_status_idx');
                $table->index(['company_id', 'severity'], 'fuel_incidents_company_severity_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_incidents_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedBigInteger('incident_id')->nullable()->index();

                $table->string('task_type', 16); // preventive|corrective
                $table->string('priority', 8); // low|medium|high
                $table->string('status', 16)->default('pending'); // pending|in_progress|done|cancelled
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->dateTime('due_at')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_maintenance_company_status_idx');
                $table->index(['company_id', 'due_at'], 'fuel_maintenance_company_due_idx');

                $table->foreign(['station_id', 'company_id'], 'fuel_maintenance_station_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_stations')
                    ->cascadeOnDelete();
                $table->foreign(['incident_id', 'company_id'], 'fuel_maintenance_incident_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_incidents')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('fuel_incident_attachments')) {
            Schema::create('fuel_incident_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('incident_id')->index();

                $table->string('path', 500);
                $table->string('original_name', 200);
                $table->string('mime_type', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->foreign(['incident_id', 'company_id'], 'fuel_attachments_incident_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('fuel_incidents')
                    ->cascadeOnDelete();
            });
        }

        $this->addChecks();
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

    private function addChecks(): void
    {
        foreach ([
            'fuel_incidents' => [
                'fuel_incidents_severity_check' => "severity IN ('low', 'medium', 'high', 'critical')",
                'fuel_incidents_status_check' => "status IN ('reported', 'assigned', 'in_progress', 'resolved', 'closed')",
                'fuel_incidents_equipment_type_check' => "equipment_type IN ('pump', 'tank', 'meter', 'other')",
            ],
            'fuel_maintenance_tasks' => [
                'fuel_maintenance_task_type_check' => "task_type IN ('preventive', 'corrective')",
                'fuel_maintenance_priority_check' => "priority IN ('low', 'medium', 'high')",
                'fuel_maintenance_status_check' => "status IN ('pending', 'in_progress', 'done', 'cancelled')",
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
