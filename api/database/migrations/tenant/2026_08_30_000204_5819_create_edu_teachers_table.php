<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5819 (EDU-003).
 *
 * edu_teachers : enseignants d'un établissement (tenant). Isolation stricte
 * via `company_id` uuid NON nullable ; `employee_id` unique PAR TENANT
 * (nullable : PostgreSQL autorise plusieurs NULL sur une UNIQUE) ; statut
 * borné (CHECK).
 *
 * `employee_id` pointe un employé RH du tenant SANS créer de FK : le lien
 * reste découplé (pattern `edu_guardians.employee_id`, #5818-3) — un
 * enseignant peut exister sans dossier RH complet, et le référentiel RH
 * n'est pas couplé aux tables scolaires.
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles (affectations) — une référence
 *     cross-tenant est une violation FK en base ;
 *   - UNIQUE(company_id, employee_id) : un employé RH ne peut être enseignant
 *     qu'UNE fois par tenant ;
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
        if (! schemaTableExists('edu_teachers')) {
            Schema::create('edu_teachers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Lien employé RH du tenant — pas de FK (pattern edu_guardians).
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('display_name', 120);
                $table->string('specialization', 100)->nullable();
                // active | inactive | archived — CHECK edu_teachers_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'employee_id'], 'edu_teachers_company_employee_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_teachers_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_teachers_company_status_idx');
                $table->index(['company_id', 'display_name'], 'edu_teachers_company_name_idx');
                $table->index(['company_id', 'created_at'], 'edu_teachers_company_created_idx');
            });

            $schema = resolveTableSchema('edu_teachers');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_teachers_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_teachers\" ADD CONSTRAINT edu_teachers_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_teachers');
    }
};
