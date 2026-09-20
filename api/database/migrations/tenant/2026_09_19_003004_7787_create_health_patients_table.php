<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7787 (patients, BC-30).
 *
 * health_patients : dossiers administratifs des patients (tenant).
 * MRN `PAT-YYYY-NNNN` séquentiel PAR TENANT ET PAR ANNÉE, généré serveur
 * (spec §4) — UNIQUE(company_id, mrn).
 *
 * PII / données médicales (spec §3) — chiffrées AU REPOS (casts `encrypted`,
 * pattern AccountingContact/EduStudent), non interrogeables en base :
 *   - `full_name` : PII nominative en clair (listes, RBAC, jamais hors tenant) ;
 *   - `birth_date_encrypted`, `phone_encrypted`, `email_encrypted`,
 *     `address_encrypted` : PII sensibles chiffrées ;
 *   - `emergency_contact_*_encrypted`, `insurance_*_encrypted` : chiffrés ;
 *   - `allergies_encrypted`, `medical_history_encrypted` : données médicales
 *     chiffrées — visibles praticiens + direction uniquement (RBAC §2).
 *
 * Cycle de vie : un patient n'est JAMAIS supprimé physiquement (spec §3) —
 * archivage via `status` (active|deceased|archived, CHECK).
 *
 * Invariants : `company_id` uuid NON nullable + UNIQUE(id, company_id) pour
 * les FK composites des tables filles (rendez-vous, consultations,
 * admissions, factures) ; CHECK `sex` et `status`.
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_patients')) {
            Schema::create('health_patients', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // PAT-YYYY-NNNN — séquentiel par tenant/année, généré serveur.
                $table->string('mrn', 30);
                // PII nominative — en clair (affichage), jamais hors tenant.
                $table->string('full_name', 191);
                // PII sensible — chiffré au repos (cast `encrypted`).
                $table->string('birth_date_encrypted', 255)->nullable();
                // male | female | other — CHECK health_patients_sex_check
                $table->string('sex', 10);
                $table->string('blood_group', 10)->nullable();
                // Coordonnées — chiffrées au repos (casts `encrypted`).
                $table->string('phone_encrypted', 255)->nullable();
                $table->string('email_encrypted', 255)->nullable();
                $table->text('address_encrypted')->nullable();
                // Contact d'urgence — chiffré au repos.
                $table->string('emergency_contact_name_encrypted', 255)->nullable();
                $table->string('emergency_contact_phone_encrypted', 255)->nullable();
                // Assurance — chiffrée au repos.
                $table->string('insurance_provider_encrypted', 255)->nullable();
                $table->string('insurance_number_encrypted', 255)->nullable();
                // Données médicales — chiffrées au repos (praticiens + direction).
                $table->text('allergies_encrypted')->nullable();
                $table->text('medical_history_encrypted')->nullable();
                // active | deceased | archived — CHECK health_patients_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'mrn'], 'health_patients_company_mrn_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_patients_id_company_unique');
                $table->index(['company_id', 'status'], 'health_patients_company_status_idx');
                $table->index(['company_id', 'full_name'], 'health_patients_company_name_idx');
                $table->index(['company_id', 'created_at'], 'health_patients_company_created_idx');
            });

            $schema = resolveTableSchema('health_patients');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_patients_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_patients\" ADD CONSTRAINT health_patients_status_check "
                    ."CHECK (status IN ('active','deceased','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_patients_sex_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_patients\" ADD CONSTRAINT health_patients_sex_check "
                    ."CHECK (sex IN ('male','female','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        // Tables dépendantes (FK composites vers `health_patients`) : elles
        // doivent partir AVANT la table parente (2BP01). Gardé : uniquement
        // si présentes, recréées par leurs propres migrations.
        Schema::dropIfExists('health_invoice_payments');
        Schema::dropIfExists('health_invoice_items');
        Schema::dropIfExists('health_invoices');
        Schema::dropIfExists('health_admissions');
        Schema::dropIfExists('health_prescription_items');
        Schema::dropIfExists('health_prescriptions');
        Schema::dropIfExists('health_consultations');
        Schema::dropIfExists('health_appointments');

        Schema::dropIfExists('health_patients');
    }
};
