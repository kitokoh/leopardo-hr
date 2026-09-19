<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7787 (HC-003, BC-30).
 *
 * health_patients : registre des patients de l'établissement (tenant).
 * Données de santé = sensibilité MAXIMALE (art. 9 RGPD).
 *
 * Classification PII :
 *   - `mrn` : n° de dossier médical `PAT-YYYY-NNNN`, généré CÔTÉ SERVEUR,
 *     unique PAR TENANT (UNIQUE company_id+mrn, soft-deletes inclus) ;
 *   - `first_name`/`last_name`/`phone` : PII en clair — nécessaires à la
 *     recherche du registre (nom, MRN, téléphone), protégées par le RBAC
 *     strict (jamais exposées hors tenant) ;
 *   - `birth_date`, `insurance_number`, `allergies`, `medical_history` :
 *     données sensibles chiffrées AU REPOS (cast `encrypted` sur le modèle,
 *     pattern edu_students.birth_date_encrypted) — non interrogeables en
 *     base (aucun index) ;
 *   - `blood_group` : donnée de santé, valeurs bornées (CHECK).
 *
 * Cycle de vie : ARCHIVAGE au lieu de suppression physique — statut
 * `archived` + soft delete (`deleted_at`) ; jamais de DELETE physique via
 * l'API (critère d'acceptation HC-003).
 *
 * Invariants portés par le schéma : `company_id` NON nullable +
 * UNIQUE(id, company_id) (FK composites futures : séjours, rendez-vous) ;
 * CHECK `status` (active|deceased|archived) ; CHECK `sex` ; CHECK
 * `blood_group` ; indexes tenant-first pour la recherche paginée.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_patients')) {
            Schema::create('health_patients', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // N° de dossier médical — généré serveur, unique par tenant.
                $table->string('mrn', 20);
                // PII nominative — en clair (recherche), jamais hors tenant.
                $table->string('first_name', 100);
                $table->string('last_name', 100);
                // female | male | other | unknown — CHECK health_patients_sex_check
                $table->string('sex', 10)->default('unknown');
                // Donnée sensible — chiffrée au repos (cast `encrypted`).
                $table->string('birth_date', 255)->nullable();
                // A+..O- — CHECK health_patients_blood_group_check
                $table->string('blood_group', 5)->nullable();
                // PII de contact — téléphone en clair (recherche registre).
                $table->string('phone', 50)->nullable();
                $table->string('email', 191)->nullable();
                $table->string('address', 255)->nullable();
                // Personne à prévenir (PII de tiers).
                $table->string('emergency_contact_name', 191)->nullable();
                $table->string('emergency_contact_phone', 50)->nullable();
                $table->string('emergency_contact_relationship', 50)->nullable();
                // Couverture d'assurance — n° d'assuré chiffré au repos.
                $table->string('insurance_provider', 191)->nullable();
                $table->string('insurance_number', 255)->nullable();
                // Données de santé (texte libre) — chiffrées au repos.
                $table->text('allergies')->nullable();
                $table->text('medical_history')->nullable();
                // active | deceased | archived — CHECK health_patients_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();
                // Archivage (jamais de suppression physique).
                $table->softDeletes();

                $table->unique(['company_id', 'mrn'], 'health_patients_company_mrn_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_patients_id_company_unique');
                $table->index(['company_id', 'status'], 'health_patients_company_status_idx');
                $table->index(['company_id', 'last_name'], 'health_patients_company_last_name_idx');
                $table->index(['company_id', 'phone'], 'health_patients_company_phone_idx');
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
                    ."CHECK (sex IN ('female','male','other','unknown')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_patients_blood_group_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_patients\" ADD CONSTRAINT health_patients_blood_group_check "
                    ."CHECK (blood_group IS NULL OR blood_group IN ('A+','A-','B+','B-','AB+','AB-','O+','O-')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_patients');
    }
};
