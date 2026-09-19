<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7789 (HC-005, BC-30).
 *
 * health_consultations : dossier médical de consultation (tenant).
 * Données de santé = sensibilité MAXIMALE (art. 9 RGPD).
 *
 * Classification PII :
 *   - `clinical_exam`, `diagnosis`, `notes` : contenu MÉDICAL en texte
 *     libre — chiffrés AU REPOS (casts `encrypted`, pattern HC-003), non
 *     interrogeables en base ;
 *   - constantes vitales (`weight_kg`, `height_cm`, `blood_pressure`,
 *     `temperature_c`, `pulse_bpm`) : données de santé structurées,
 *     protégées par le RBAC strict (réception JAMAIS — critère HC-005) ;
 *   - `reason` : motif de consultation (donnée de santé, RBAC strict).
 *
 * FK COMPOSITES (id, company_id) vers patients, praticiens et rendez-vous :
 * une consultation cross-tenant est une violation FK en base (critère
 * d'acceptation HC-005 « patient du même tenant uniquement »).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_consultations')) {
            Schema::create('health_consultations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('appointment_id')->nullable();
                $table->timestamp('consulted_at');
                // Motif — donnée de santé (RBAC strict, jamais la réception).
                $table->string('reason', 255);
                // Contenu médical (texte libre) — chiffré au repos.
                $table->text('clinical_exam')->nullable();
                $table->text('diagnosis')->nullable();
                // Constantes vitales — données de santé structurées.
                $table->decimal('weight_kg', 5, 2)->nullable();
                $table->decimal('height_cm', 5, 1)->nullable();
                $table->string('blood_pressure', 20)->nullable();
                $table->decimal('temperature_c', 4, 1)->nullable();
                $table->unsignedSmallInteger('pulse_bpm')->nullable();
                // Notes du praticien — chiffrées au repos.
                $table->text('notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_consultations_id_company_unique');
                // Historique médical par patient et par praticien.
                $table->index(['company_id', 'patient_id', 'consulted_at'], 'health_consultations_company_patient_idx');
                $table->index(['company_id', 'practitioner_id', 'consulted_at'], 'health_consultations_company_practitioner_idx');

                // Cross-tenant impossible : patient, praticien et rendez-vous
                // référencés doivent exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_consultations_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_consultations_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                // Pas de SET NULL (FK composite : il annulerait AUSSI
                // company_id) — le rendez-vous reste tant qu'une
                // consultation le référence.
                $table->foreign(['appointment_id', 'company_id'], 'health_consultations_appointment_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_appointments');
            });

            $schema = resolveTableSchema('health_consultations');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_consultations_pulse_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_consultations\" ADD CONSTRAINT health_consultations_pulse_check "
                    .'CHECK (pulse_bpm IS NULL OR pulse_bpm BETWEEN 1 AND 400); END IF; END $$'
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_consultations');
    }
};
