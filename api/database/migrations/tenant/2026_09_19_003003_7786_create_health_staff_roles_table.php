<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (référentiel, BC-30).
 *
 * health_staff_roles : rôles opérationnels non médicaux du tenant
 * (réception, facturation) portés par un employé RH (`employee_id` SANS FK
 * dure — pattern edu_teachers : lien découplé du référentiel RH).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) ;
 *   - UNIQUE(company_id, employee_id, role) : un employé ne porte un rôle
 *     donné qu'UNE fois par tenant ;
 *   - CHECK `role` (reception|billing) — valeurs inconnues rejetées.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_staff_roles')) {
            Schema::create('health_staff_roles', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Lien employé RH du tenant — pas de FK (pattern edu_teachers).
                $table->unsignedBigInteger('employee_id');
                // reception | billing — CHECK health_staff_roles_role_check
                $table->string('role', 20);
                $table->timestamps();

                $table->unique(['company_id', 'employee_id', 'role'], 'health_staff_roles_company_employee_role_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_staff_roles_id_company_unique');
                $table->index(['company_id', 'role'], 'health_staff_roles_company_role_idx');
                $table->index(['company_id', 'employee_id'], 'health_staff_roles_company_employee_idx');
            });

            $schema = resolveTableSchema('health_staff_roles');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_staff_roles_role_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_staff_roles\" ADD CONSTRAINT health_staff_roles_role_check "
                    ."CHECK (role IN ('reception','billing')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_staff_roles');
    }
};
