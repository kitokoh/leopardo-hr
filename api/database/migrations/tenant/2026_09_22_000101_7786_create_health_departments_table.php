<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_departments : services médicaux d'un établissement de santé
 * (cardiologie, maternité, urgences…), tenant-scoped. Isolation stricte via
 * `company_id` uuid NON nullable (BelongsToCompany) ; `code` unique PAR
 * TENANT ; statut borné (CHECK).
 *
 * Invariants portés par le schéma :
 *   - `company_id` NON nullable + UNIQUE(id, company_id) : clé d'intégrité
 *     des FK composites des tables filles (health_rooms) — une référence
 *     cross-tenant est une violation FK en base ;
 *   - CHECK `status` (active|inactive|archived) — valeurs inconnues rejetées ;
 *   - indexes tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_departments')) {
            Schema::create('health_departments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 50);
                $table->string('name', 191);
                $table->string('description', 255)->nullable();
                // active | inactive | archived — CHECK health_departments_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_departments_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_departments_id_company_unique');
                $table->index(['company_id', 'status'], 'health_departments_company_status_idx');
                $table->index(['company_id', 'name'], 'health_departments_company_name_idx');
            });

            $schema = resolveTableSchema('health_departments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_departments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_departments\" ADD CONSTRAINT health_departments_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_departments');
    }
};
