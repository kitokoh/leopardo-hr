<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7789 (HC-005, BC-30).
 *
 * health_prescriptions : ordonnance émise lors d'une consultation
 * (tenant). `patient_id` est porté en propre (dénormalisé mais garanti par
 * FK composite) pour l'HISTORIQUE PAR PATIENT — critère d'acceptation
 * HC-005. Les lignes de médicaments vivent dans
 * `health_prescription_items` (≥ 1 ligne, validé côté application).
 *
 * Données de santé (RBAC strict, réception JAMAIS) ; `notes` chiffrées au
 * repos (cast `encrypted`).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_prescriptions')) {
            Schema::create('health_prescriptions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('consultation_id');
                $table->unsignedBigInteger('patient_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->timestamp('prescribed_at');
                // Notes de l'ordonnance — chiffrées au repos.
                $table->text('notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_prescriptions_id_company_unique');
                // Historique des prescriptions PAR PATIENT (critère HC-005).
                $table->index(['company_id', 'patient_id', 'prescribed_at'], 'health_prescriptions_company_patient_idx');
                $table->index(['company_id', 'consultation_id'], 'health_prescriptions_company_consultation_idx');

                // Cross-tenant impossible (FK composites).
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
    }

    public function down(): void
    {
        Schema::dropIfExists('health_prescriptions');
    }
};
