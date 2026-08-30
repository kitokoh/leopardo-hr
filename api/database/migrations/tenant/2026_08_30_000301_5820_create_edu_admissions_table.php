<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5820 (EDU-004).
 *
 * edu_admissions : dossier d'inscription (admission) d'un candidat (tenant).
 * Le dossier précède l'élève : la conversion vers `edu_students` est faite
 * par `AdmissionService::convert()` (idempotente) et tracée ici
 * (`student_id`, `decided_by`, `decided_at`, statut `enrolled`).
 *
 * Découplage CRM : `contact_reference` pointe une ressource externe au
 * contexte scolaire (ex. contact CRM client) SANS créer de FK — le lien
 * reste découplé (principe spec §6.4 : le CRM client n'est pas couplé aux
 * tables scolaires). Le CRM commercial plateforme (`marketing_leads`) n'est
 * JAMAIS accessible depuis EduManager.
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 *   - `applicant_name` : PII nominative en clair (affichage dossier),
 *     protégée par RBAC — jamais exposée hors tenant ;
 *   - `contact_reference` : PII sensible (email/téléphone du candidat)
 *     chiffrée AU REPOS (cast `encrypted` sur le modèle) — non indexable ;
 *   - `consent_marketing` / `consent_at` : traçabilité du consentement
 *     contact (RGPD) ;
 *   - `metadata` : blob JSON libre chiffré au repos (cast `encrypted:array`).
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles — une référence cross-tenant est
 *     une violation FK en base ;
 *   - `admission_number` UNIQUE par tenant (UNIQUE company_id+admission_number) ;
 *   - FK composite (student_id, company_id) → edu_students(id, company_id)
 *     ON DELETE SET NULL : si l'élève est supprimé, le dossier d'inscription
 *     reste consultable (student_id NULL). Note : `company_id` restant NON
 *     nullable, la suppression d'un élève référencé échouera en base —
 *     comportement voulu, la suppression logique passe par `status=archived` ;
 *   - FK composite (academic_year_id, company_id) → edu_academic_years :
 *     créée si la table existe déjà (migration #5819-1, préfixe 000201 <
 *     000301, livrée EDU-003) — la garde schemaTableExists() rend la
 *     migration robuste à tout ordre de rejeu ;
 *   - CHECK `status` (pending|admitted|rejected|enrolled|cancelled) — valeurs
 *     inconnues rejetées ;
 *   - index tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_admissions')) {
            Schema::create('edu_admissions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Élève issu de la conversion — NULL tant que le dossier n'est
                // pas converti (le dossier reste lisible après conversion).
                $table->unsignedBigInteger('student_id')->nullable();
                $table->string('admission_number', 50);
                // PII nominative — en clair (affichage), jamais hors tenant.
                $table->string('applicant_name', 191);
                // PII sensible — chiffré au repos (cast `encrypted`), PAS de FK
                // (pattern edu_guardians : lien découplé du CRM client).
                $table->string('contact_reference', 255)->nullable();
                // Année scolaire visée — FK créée si la table existe (EDU-003).
                $table->unsignedBigInteger('academic_year_id')->nullable();
                // pending | admitted | rejected | enrolled | cancelled
                $table->string('status', 20)->default('pending');
                // Consentement contact (marketing) — tracé RGPD.
                $table->boolean('consent_marketing')->default(false);
                $table->timestampTz('consent_at')->nullable();
                $table->timestampTz('submitted_at')->nullable();
                $table->timestampTz('decided_at')->nullable();
                $table->unsignedInteger('decided_by')->nullable();
                $table->jsonb('metadata')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'admission_number'], 'edu_admissions_company_number_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_admissions_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_admissions_company_status_idx');
                $table->index(['company_id', 'submitted_at'], 'edu_admissions_company_submitted_idx');

                // Cross-tenant impossible : la paire (student_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['student_id', 'company_id'], 'edu_admissions_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->nullOnDelete();

                // edu_academic_years est livrée par EDU-003 (préfixe 000201 <
                // 000301) : la FK n'est créée que si la table existe déjà —
                // garde robuste à l'ordre de rejeu (idempotence F-17).
                if (schemaTableExists('edu_academic_years')) {
                    $table->foreign(['academic_year_id', 'company_id'], 'edu_admissions_academic_year_company_fk')
                        ->references(['id', 'company_id'])
                        ->on('edu_academic_years')
                        ->nullOnDelete();
                }
            });

            $schema = resolveTableSchema('edu_admissions');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_admissions_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_admissions\" ADD CONSTRAINT edu_admissions_status_check "
                    ."CHECK (status IN ('pending','admitted','rejected','enrolled','cancelled')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_admissions');
    }
};
