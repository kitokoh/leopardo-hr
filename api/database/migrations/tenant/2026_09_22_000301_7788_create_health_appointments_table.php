<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7788 (HC-004, BC-30).
 *
 * health_appointments : rendez-vous patient ↔ praticien, tenant-scoped.
 * FK COMPOSITES (id, company_id) vers patients, praticiens et services :
 * un rendez-vous cross-tenant est une violation FK en base.
 *
 * Invariants portés par le schéma :
 *   - CHECK statut borné (scheduled|confirmed|checked_in|completed|
 *     cancelled|no_show) — cycle de vie validé côté application
 *     (transitions HealthAppointment::TRANSITIONS, 422 sinon) ;
 *   - CHECK ends_at > starts_at (créneau non dégénéré) ;
 *   - le NON-chevauchement praticien (409) est contrôlé côté application
 *     dans une transaction avec lockForUpdate (un EXCLUDE Postgres
 *     nécessiterait btree_gist — hors pattern repo).
 *
 * Index tenant-first pour l'agenda praticien (company_id, practitioner_id,
 * starts_at) et l'historique patient.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_appointments')) {
            Schema::create('health_appointments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('department_id')->nullable();
                $table->timestamp('starts_at');
                $table->timestamp('ends_at');
                // Motif de consultation — donnée de santé (RBAC strict).
                $table->string('reason', 255);
                // scheduled | confirmed | checked_in | completed | cancelled
                // | no_show — CHECK health_appointments_status_check
                $table->string('status', 20)->default('scheduled');
                $table->text('notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_appointments_id_company_unique');
                // Agenda praticien (jour/semaine) et détection de conflits.
                $table->index(['company_id', 'practitioner_id', 'starts_at'], 'health_appointments_company_practitioner_starts_idx');
                $table->index(['company_id', 'patient_id', 'starts_at'], 'health_appointments_company_patient_starts_idx');
                $table->index(['company_id', 'status'], 'health_appointments_company_status_idx');
                $table->index(['company_id', 'starts_at'], 'health_appointments_company_starts_idx');

                // Cross-tenant impossible : patient, praticien et service
                // référencés doivent exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_appointments_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_appointments_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                // Pas de SET NULL : sur une FK composite il annulerait AUSSI
                // company_id — NO ACTION (le service reste supprimable tant
                // qu'aucun rendez-vous ne le référence).
                $table->foreign(['department_id', 'company_id'], 'health_appointments_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments');
            });

            $schema = resolveTableSchema('health_appointments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_appointments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_appointments\" ADD CONSTRAINT health_appointments_status_check "
                    ."CHECK (status IN ('scheduled','confirmed','checked_in','completed','cancelled','no_show')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_appointments_time_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_appointments\" ADD CONSTRAINT health_appointments_time_check "
                    .'CHECK (ends_at > starts_at); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_appointments');
    }
};
