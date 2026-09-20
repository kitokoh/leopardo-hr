<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7789 (consultations & prescriptions, BC-30).
 *
 *   - `health_consultations` : contenu MÉDICAL d'une consultation —
 *     examen clinique, diagnostic, constantes vitales (jsonb chiffré :
 *     weight_kg, height_cm, blood_pressure, temperature_c, pulse_bpm) et
 *     notes chiffrés AU REPOS (casts `encrypted` / `encrypted:array`) —
 *     visibles praticiens + direction UNIQUEMENT (RBAC §2, jamais la
 *     réception ni la facturation) ;
 *   - `health_prescriptions` : ordonnances liées à une consultation ;
 *   - `health_prescription_items` : lignes de prescription (médicament,
 *     posologie, fréquence, durée, instructions).
 *
 * Cycle de vie : une consultation n'est JAMAIS supprimée physiquement
 * (spec §3) — la suppression logique relève du service.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) ;
 *   - FK composites (patient_id/practitioner_id/appointment_id/
 *     consultation_id/prescription_id, company_id) : tout contenu médical
 *     croisant les tenants est STRUCTURELLEMENT impossible ;
 *   - index tenant-first pour dossiers patient et vues praticien.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
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
                $table->timestampTz('consulted_at');
                $table->string('reason', 255)->nullable();
                // Contenu médical — chiffré au repos (casts `encrypted`).
                $table->text('clinical_exam_encrypted')->nullable();
                $table->text('diagnosis_encrypted')->nullable();
                // Constantes vitales — jsonb chiffré (cast `encrypted:array`).
                $table->text('vitals_encrypted')->nullable();
                $table->text('notes_encrypted')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_consultations_id_company_unique');
                $table->index(['company_id', 'patient_id'], 'health_consultations_company_patient_idx');
                $table->index(['company_id', 'practitioner_id'], 'health_consultations_company_pract_idx');
                $table->index(['company_id', 'consulted_at'], 'health_consultations_company_consulted_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_consultations_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_consultations_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                $table->foreign(['appointment_id', 'company_id'], 'health_consultations_appointment_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_appointments')
                    ->nullOnDelete();
            });
        }

        if (! schemaTableExists('health_prescriptions')) {
            Schema::create('health_prescriptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('consultation_id');
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->timestampTz('prescribed_at');
                // Notes médicales — chiffrées au repos (cast `encrypted`).
                $table->text('notes_encrypted')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_prescriptions_id_company_unique');
                $table->index(['company_id', 'consultation_id'], 'health_prescriptions_company_consultation_idx');
                $table->index(['company_id', 'patient_id'], 'health_prescriptions_company_patient_idx');
                $table->index(['company_id', 'practitioner_id'], 'health_prescriptions_company_pract_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['consultation_id', 'company_id'], 'health_prescriptions_consultation_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_consultations')
                    ->cascadeOnDelete();
                $table->foreign(['patient_id', 'company_id'], 'health_prescriptions_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_prescriptions_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('health_prescription_items')) {
            Schema::create('health_prescription_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('prescription_id');
                $table->string('medication', 191);
                $table->string('dosage', 100)->nullable();
                $table->string('frequency', 100)->nullable();
                $table->string('duration', 100)->nullable();
                $table->text('instructions')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_prescription_items_id_company_unique');
                $table->index(['company_id', 'prescription_id'], 'health_prescription_items_company_prescription_idx');

                // Cross-tenant impossible : la paire (prescription_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['prescription_id', 'company_id'], 'health_prescription_items_prescription_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_prescriptions')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_prescription_items');
        Schema::dropIfExists('health_prescriptions');
        Schema::dropIfExists('health_consultations');
    }
};
