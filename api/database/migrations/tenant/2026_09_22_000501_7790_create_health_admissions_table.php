<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7790 (HC-006, BC-30).
 *
 * health_admissions : hospitalisations (admission → séjour → transfert →
 * sortie), tenant-scoped. Données de séjour = données de santé (RBAC).
 *
 * Invariants portés par le SCHÉMA (pas seulement l'application) :
 *   - INDEX UNIQUE PARTIEL Postgres (company_id, bed_id) sur les séjours
 *     ACTIFS (admitted|transferred) : un lit ne porte JAMAIS deux séjours
 *     actifs — la double admission concurrente est une violation d'unicité
 *     en base (pattern #7717, l'API répond 409 HEALTH_BED_UNAVAILABLE) ;
 *   - INDEX UNIQUE PARTIEL (company_id, patient_id) actifs : un patient n'a
 *     qu'UN séjour actif à la fois (409 HEALTH_PATIENT_ALREADY_ADMITTED) ;
 *   - CHECK statut borné admitted|transferred|discharged ;
 *   - FK COMPOSITES (id, company_id) vers patients, praticiens, services et
 *     lits : une admission cross-tenant est une violation FK en base.
 *
 * Traçabilité du transfert : `transferred_from_bed_id` + `transferred_at`
 * gardent le lit d'origine du DERNIER transfert (statut `transferred`).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
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
                // Praticien RÉFÉRENT du séjour.
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('department_id');
                $table->unsignedBigInteger('bed_id');
                // Lit d'origine du dernier transfert (traçabilité HC-006).
                $table->unsignedBigInteger('transferred_from_bed_id')->nullable();
                // Motif d'admission — donnée de santé (RBAC strict).
                $table->string('reason', 255);
                $table->timestamp('admitted_at');
                $table->timestamp('expected_discharge_at')->nullable();
                $table->timestamp('discharged_at')->nullable();
                $table->timestamp('transferred_at')->nullable();
                // admitted | transferred | discharged — CHECK ci-dessous.
                $table->string('status', 20)->default('admitted');
                // Notes de sortie — chiffrées au repos (cast `encrypted`).
                $table->text('discharge_notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_admissions_id_company_unique');
                $table->index(['company_id', 'status'], 'health_admissions_company_status_idx');
                $table->index(['company_id', 'department_id', 'status'], 'health_admissions_company_department_idx');
                $table->index(['company_id', 'patient_id', 'admitted_at'], 'health_admissions_company_patient_idx');

                // Cross-tenant impossible (FK composites).
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
                    ->on('health_departments');
                $table->foreign(['bed_id', 'company_id'], 'health_admissions_bed_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_beds');
            });

            $schema = resolveTableSchema('health_admissions');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_admissions_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_admissions\" ADD CONSTRAINT health_admissions_status_check "
                    ."CHECK (status IN ('admitted','transferred','discharged')); END IF; END $$"
                );
                // Un lit = AU PLUS un séjour actif ; un patient = AU PLUS un
                // séjour actif (index uniques PARTIELS, pattern #7717).
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS health_admissions_active_bed_unique '
                    ."ON \"{$schema}\".\"health_admissions\" (company_id, bed_id) "
                    ."WHERE status IN ('admitted','transferred')"
                );
                DB::statement(
                    'CREATE UNIQUE INDEX IF NOT EXISTS health_admissions_active_patient_unique '
                    ."ON \"{$schema}\".\"health_admissions\" (company_id, patient_id) "
                    ."WHERE status IN ('admitted','transferred')"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_admissions');
    }
};
