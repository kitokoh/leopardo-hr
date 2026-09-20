<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7790 (hospitalisations, BC-30).
 *
 * health_admissions : hospitalisations d'un patient sur un lit (tenant).
 *
 * Invariants métier (spec §4, portés par le service, en transaction +
 * verrou) : lit `free` requis à l'admission → lit `occupied` ; double
 * admission sur lit occupé → 409 HEALTH_BED_OCCUPIED ; transfert = ancien
 * lit libéré + nouveau occupé ; sortie = `discharged` + lit libéré.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) ;
 *   - FK composites (patient_id/practitioner_id/department_id/bed_id,
 *     company_id) : une admission croisant les tenants est
 *     STRUCTURELLEMENT impossible ;
 *   - CHECK `status` (admitted|transferred|discharged) ;
 *   - index tenant-first (statut, patient, lit) pour listes et occupation.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_admissions')) {
            Schema::create('health_admissions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('patient_id');
                // Praticien référent de l'hospitalisation.
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('bed_id');
                $table->string('reason', 255)->nullable();
                $table->timestampTz('admitted_at');
                $table->timestampTz('expected_discharge_at')->nullable();
                $table->timestampTz('discharged_at')->nullable();
                // admitted | transferred | discharged — CHECK status
                $table->string('status', 20)->default('admitted');
                $table->text('discharge_notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_admissions_id_company_unique');
                $table->index(['company_id', 'status'], 'health_admissions_company_status_idx');
                $table->index(['company_id', 'patient_id'], 'health_admissions_company_patient_idx');
                $table->index(['company_id', 'bed_id'], 'health_admissions_company_bed_idx');
                $table->index(['company_id', 'admitted_at'], 'health_admissions_company_admitted_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_admissions_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_admissions_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                $table->foreign(['department_id', 'company_id'], 'health_admissions_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments')
                    ->cascadeOnDelete();
                $table->foreign(['bed_id', 'company_id'], 'health_admissions_bed_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_beds')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_admissions');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_admissions_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_admissions\" ADD CONSTRAINT health_admissions_status_check "
                    ."CHECK (status IN ('admitted','transferred','discharged')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_admissions');
    }
};
