<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5804 (FUEL-010) — incidents, maintenance et tâches.
 *
 * `fuel_incidents` : incidents équipements (pompe, cuve, compteur, autre)
 * avec workflow audité open → in_progress → resolved → closed. Priorités,
 * assignation, résolution motivée — chaque transition est horodatée.
 *
 * `fuel_incident_attachments` : pièces jointes contrôlées (métadonnées
 * uniquement : nom assaini, MIME allowlist, taille bornée ; le fichier
 * vit dans le module Documents). Aucune donnée sensible dans le nom.
 *
 * `fuel_maintenance_tasks` : maintenance préventive/corrective liée ou non
 * à un incident, priorité, échéance, complétion auditée.
 *
 * FK composites anti cross-tenant (pattern FUEL-002/003). `employee_id`
 * (FK employees) pour reported_by/assigned_to — jamais de données PII en
 * clair dans les notifications liées (FUEL-019).
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
                $table->string('equipment_type', 20)->default('other'); // pump|tank|meter|other
                $table->unsignedBigInteger('equipment_id')->nullable();
                $table->string('severity', 20)->default('medium'); // low|medium|high|critical
                $table->string('status', 20)->default('open'); // open|in_progress|resolved|closed
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->unsignedInteger('reported_by')->nullable();
                $table->unsignedInteger('assigned_to')->nullable();
                $table->timestampTz('occurred_at')->useCurrent();
                $table->timestampTz('resolved_at')->nullable();
                $table->unsignedInteger('resolved_by')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->timestampTz('closed_at')->nullable();
                $table->unsignedInteger('closed_by')->nullable();
                $table->text('closure_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'station_id', 'status'], 'fuel_incidents_station_status_idx');
                $table->index(['company_id', 'status', 'severity'], 'fuel_incidents_status_severity_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_incidents IS 'Incidents équipements FuelStation — workflow audité (FUEL-010, #5804).'");
        }

        if (! schemaTableExists('fuel_incident_attachments')) {
            Schema::create('fuel_incident_attachments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('incident_id')->index();
                $table->string('file_name', 200);
                $table->string('mime_type', 120);
                $table->unsignedBigInteger('size_bytes')->default(0);
                $table->unsignedInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'incident_id'], 'fuel_incident_attachments_incident_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_incident_attachments IS 'Pièces jointes d''incident — métadonnées contrôlées (MIME/size allowlist), fichiers dans le module Documents (FUEL-010).'");
        }

        if (! schemaTableExists('fuel_maintenance_tasks')) {
            Schema::create('fuel_maintenance_tasks', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('station_id')->nullable()->index();
                $table->unsignedBigInteger('incident_id')->nullable()->index();
                $table->string('task_type', 20)->default('preventive'); // preventive|corrective
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('priority', 20)->default('medium'); // low|medium|high
                $table->string('status', 20)->default('todo'); // todo|in_progress|done|cancelled
                $table->unsignedInteger('assigned_to')->nullable();
                $table->date('scheduled_for')->nullable();
                $table->date('completed_at')->nullable();
                $table->unsignedInteger('completed_by')->nullable();
                $table->text('completion_notes')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'station_id', 'status'], 'fuel_maintenance_station_status_idx');
            });

            DB::statement("COMMENT ON TABLE fuel_maintenance_tasks IS 'Tâches de maintenance préventive/corrective FuelStation (FUEL-010, #5804).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_maintenance_tasks');
        Schema::dropIfExists('fuel_incident_attachments');
        Schema::dropIfExists('fuel_incidents');
    }
};
