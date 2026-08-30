<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5818 (EDU-002).
 *
 * edu_guardians : responsables légaux d'élèves (tenant). Un responsable est
 * rattaché à UN OU PLUSIEURS élèves via `edu_student_guardians` (relation
 * autorisée : voir la migration #5818-4 et `EduStudentPolicy`).
 *
 * `contact_reference` pointe une ressource externe au contexte scolaire
 * (ex. contact CRM client) SANS créer de FK — le lien reste découplé
 * (principe spec §6.4 : le CRM client n'est pas couplé aux tables scolaires).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) :
 *   - `first_name` / `last_name` : PII nominative en clair (affichage
 *     portail guardian), protégée par RBAC ;
 *   - `contact_reference` : PII sensible (email/téléphone du responsable)
 *     chiffrée AU REPOS (cast `encrypted`) — non indexable ;
 *   - `verified_at` : traçabilité du consentement/validation RGPD.
 *
 * Invariants : `company_id` NON nullable + UNIQUE(id, company_id) pour les
 * FK composites (student_guardians) ; CHECK `relationship_code` borné ;
 * index tenant-first. Gardes F-17 : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_guardians')) {
            Schema::create('edu_guardians', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Lien identité portail guardian (EDU-013) — employé du tenant.
                // Pas de FK : pattern CRM `owner_id` (bigint, PK employees.id).
                $table->unsignedBigInteger('employee_id')->nullable();
                // PII nominative — en clair (affichage), jamais hors tenant.
                $table->string('first_name', 80)->nullable();
                $table->string('last_name', 80)->nullable();
                // PII sensible — chiffré au repos (cast `encrypted`).
                $table->string('contact_reference', 255)->nullable();
                // parent | guardian | other — CHECK edu_guardians_relationship_check
                $table->string('relationship_code', 30)->default('parent');
                $table->timestampTz('verified_at')->nullable();
                $table->timestamps();

                $table->unique(['id', 'company_id'], 'edu_guardians_id_company_unique');
                $table->index(['company_id', 'employee_id'], 'edu_guardians_company_employee_idx');
                $table->index(['company_id', 'verified_at'], 'edu_guardians_company_verified_idx');
                $table->index(['company_id', 'created_at'], 'edu_guardians_company_created_idx');
            });

            $schema = resolveTableSchema('edu_guardians');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_guardians_relationship_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_guardians\" ADD CONSTRAINT edu_guardians_relationship_check "
                    ."CHECK (relationship_code IN ('parent','guardian','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_guardians');
    }
};
