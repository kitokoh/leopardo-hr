<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5819 (EDU-003).
 *
 * edu_academic_years : années scolaires d'un établissement (tenant).
 * Isolation stricte via `company_id` uuid NON nullable (BelongsToCompany) ;
 * nom unique PAR TENANT ; statut borné (CHECK).
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles (classes, affectations) — une
 *     référence cross-tenant est une violation FK en base ;
 *   - CHECK `status` (active|inactive|archived) — valeurs inconnues rejetées ;
 *   - CHECK `period` (start_date < end_date) — période scolaire cohérente,
 *     une année inversée est STRUCTURELLEMENT impossible ;
 *   - indexes tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_academic_years')) {
            Schema::create('edu_academic_years', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                $table->date('start_date');
                $table->date('end_date');
                // active | inactive | archived — CHECK edu_academic_years_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'edu_academic_years_company_name_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_academic_years_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_academic_years_company_status_idx');
                $table->index(['company_id', 'start_date'], 'edu_academic_years_company_start_idx');
                $table->index(['company_id', 'created_at'], 'edu_academic_years_company_created_idx');
            });

            $schema = resolveTableSchema('edu_academic_years');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_academic_years_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_academic_years\" ADD CONSTRAINT edu_academic_years_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_academic_years_period_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_academic_years\" ADD CONSTRAINT edu_academic_years_period_check "
                    ."CHECK (start_date < end_date); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_academic_years');
    }
};
