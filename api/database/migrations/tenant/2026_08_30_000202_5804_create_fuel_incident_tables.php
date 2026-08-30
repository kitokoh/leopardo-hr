<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5804 — Incidents, maintenance et tâches FuelStation (FUEL-010, BC-15 FUEL).
 *
 * `fuel_incidents` : signalement d'incident équipement (priorité, statut,
 * assignation, résolution) — transitions auditées, permissions par site.
 * `fuel_maintenance_tasks` : maintenance préventive/corrective (priorité,
 * échéance, assignation, achèvement) — même discipline d'audit.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('fuel_incidents')) {
            Schema::create('fuel_incidents', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('equipment_type', 20)->nullable(); // pump|tank|meter|site|other
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('severity', 20)->default('medium'); // low|medium|high|critical
                $table->string('status', 20)->default('open'); // open|assigned|in_progress|resolved|cancelled
                $table->unsignedInteger('assigned_to')->nullable()->index();
                $table->unsignedInteger('reported_by')->index();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->text('resolution_note')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_incidents_company_status_idx');
                $table->index(['company_id', 'station_id', 'status'], 'fuel_incidents_company_station_status_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_incidents IS 'Incidents équipements FuelStation — workflow audité, permissions par site (FUEL-010 #5804).'");
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->string('equipment_type', 20)->nullable();
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('type', 20); // preventive|corrective
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('medium'); // low|medium|high
                $table->string('status', 20)->default('open'); // open|in_progress|completed|cancelled
                $table->unsignedInteger('assigned_to')->nullable()->index();
                $table->date('due_date')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->timestampTz('completed_at')->nullable();
                $table->text('completion_note')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'fuel_maint_tasks_company_status_idx');
                $table->index(['company_id', 'assigned_to', 'status'], 'fuel_maint_tasks_company_assignee_status_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_maintenance_tasks IS 'Tâches de maintenance FuelStation — préventive/corrective, priorisée, auditées (FUEL-010 #5804).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_maintenance_tasks');
        Schema::dropIfExists('fuel_incidents');
    }
};
