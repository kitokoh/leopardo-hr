<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7788 (rendez-vous, BC-30).
 *
 * health_appointments : agenda des rendez-vous patient ↔ praticien (tenant).
 *
 * Invariants métier (spec §4, portés par le service) :
 *   - chevauchement praticien interdit (409 HEALTH_APPOINTMENT_CONFLICT) ;
 *   - transitions : scheduled→confirmed|cancelled ;
 *     confirmed→checked_in|cancelled|no_show ; checked_in→completed ;
 *     terminaux : completed, cancelled, no_show.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) ;
 *   - FK composites (patient_id/practitioner_id/department_id, company_id) :
 *     un rendez-vous croisant les tenants est STRUCTURELLEMENT impossible ;
 *   - CHECK `status` (scheduled|confirmed|checked_in|completed|cancelled|
 *     no_show) — valeurs inconnues rejetées ;
 *   - index (company_id, practitioner_id, starts_at) pour la détection de
 *     conflits d'agenda et les vues praticien.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
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
                $table->timestampTz('starts_at');
                $table->timestampTz('ends_at');
                $table->string('reason', 255)->nullable();
                // scheduled | confirmed | checked_in | completed | cancelled | no_show
                $table->string('status', 20)->default('scheduled');
                $table->text('notes')->nullable();
                $table->timestamps();

                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_appointments_id_company_unique');
                $table->index(['company_id', 'practitioner_id', 'starts_at'], 'health_appointments_company_pract_starts_idx');
                $table->index(['company_id', 'patient_id'], 'health_appointments_company_patient_idx');
                $table->index(['company_id', 'status'], 'health_appointments_company_status_idx');
                $table->index(['company_id', 'starts_at'], 'health_appointments_company_starts_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['patient_id', 'company_id'], 'health_appointments_patient_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_patients')
                    ->cascadeOnDelete();
                $table->foreign(['practitioner_id', 'company_id'], 'health_appointments_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                $table->foreign(['department_id', 'company_id'], 'health_appointments_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('health_appointments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_appointments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_appointments\" ADD CONSTRAINT health_appointments_status_check "
                    ."CHECK (status IN ('scheduled','confirmed','checked_in','completed','cancelled','no_show')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        // Table dépendante (FK composite appointment_id) : part AVANT (2BP01).
        Schema::dropIfExists('health_consultations');

        Schema::dropIfExists('health_appointments');
    }
};
