<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5818 (EDU-002).
 *
 * edu_students : élèves d'un établissement (tenant). Isolation stricte via
 * `company_id` uuid NON nullable ; `student_number` unique PAR TENANT ;
 * statut borné (CHECK).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 *   - `display_name` : PII nominative, nécessaire en clair pour les listes
 *     et bulletins — protégée par le RBAC (jamais exposée hors tenant) ;
 *   - `birth_date_encrypted` : PII sensible chiffrée AU REPOS (cast
 *     `encrypted` sur le modèle, pattern `AccountingContact`) — non
 *     interrogeable en base (pas d'index) ;
 *   - `metadata` : blob JSON libre chiffré au repos (cast
 *     `encrypted:array`), borné côté serveur (EDU-006+).
 *
 * Cycle de vie RGPD : suppression/logique via statut `archived`, droit à
 * l'effacement via le registre `privacy_requests` existant (pattern CRM).
 *
 * Invariants : `company_id` NON nullable + UNIQUE(id, company_id) pour les
 * FK composites des tables filles (student_guardians) ; CHECK status.
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_students')) {
            Schema::create('edu_students', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('student_number', 50);
                // PII nominative — en clair (affichage), jamais hors tenant.
                $table->string('display_name', 191);
                // PII sensible — chiffré au repos (cast `encrypted`).
                $table->string('birth_date_encrypted', 255)->nullable();
                // active | inactive | archived — CHECK edu_students_status_check
                $table->string('status', 20)->default('active');
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'student_number'], 'edu_students_company_number_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_students_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_students_company_status_idx');
                $table->index(['company_id', 'display_name'], 'edu_students_company_name_idx');
                $table->index(['company_id', 'created_at'], 'edu_students_company_created_idx');
            });

            $schema = resolveTableSchema('edu_students');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_students_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_students\" ADD CONSTRAINT edu_students_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_students');
    }
};
