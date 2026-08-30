<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5818 (EDU-002).
 *
 * edu_campuses : campus / sites d'un établissement scolaire (tenant).
 * Isolation stricte via `company_id` uuid NON nullable (BelongsToCompany) ;
 * statut borné (CHECK) ; `code` unique PAR TENANT (UNIQUE company_id+code).
 *
 * PII : l'adresse d'un campus est une donnée de localisation d'établissement
 * (classée « personnel » dans le registre RGPD — voir
 * `docs/architecture/EDUMANAGER_DONNEES.md`), conservée en clair pour
 * l'affichage administratif.
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles (students/guardians) — une
 *     référence cross-tenant est une violation FK en base ;
 *   - CHECK `status` (active|inactive|archived) — valeurs inconnues rejetées ;
 *   - indexes tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_campuses')) {
            Schema::create('edu_campuses', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 50);
                $table->string('name', 191);
                $table->string('address', 255)->nullable();
                $table->string('timezone', 100)->default('UTC');
                // active | inactive | archived — CHECK edu_campuses_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'edu_campuses_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_campuses_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_campuses_company_status_idx');
                $table->index(['company_id', 'name'], 'edu_campuses_company_name_idx');
                $table->index(['company_id', 'created_at'], 'edu_campuses_company_created_idx');
            });

            $schema = resolveTableSchema('edu_campuses');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_campuses_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_campuses\" ADD CONSTRAINT edu_campuses_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_campuses');
    }
};
