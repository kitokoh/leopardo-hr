<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (référentiel, BC-30).
 *
 * Équipe médicale de l'établissement (tenant) :
 *   - `health_specialties` : spécialités médicales (code unique PAR TENANT,
 *     seed standard à l'activation de la solution) ;
 *   - `health_practitioners` : praticiens liés à un employé RH du tenant
 *     (`employee_id` SANS FK dure — pattern edu_teachers : lien découplé du
 *     référentiel RH) ; titre borné (dr|pr|midwife|nurse|other) ;
 *   - `health_practitioner_specialties` : affectation n-n praticien →
 *     spécialité, unique PAR TENANT.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites des tables filles ;
 *   - UNIQUE(company_id, employee_id) : un employé RH ne peut être
 *     praticien qu'UNE fois par tenant ;
 *   - FK composites (practitioner_id/specialty_id/department_id, company_id) :
 *     toute affectation croisant les tenants est STRUCTURELLEMENT impossible ;
 *   - CHECK `title` et `status` — valeurs inconnues rejetées.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_specialties')) {
            Schema::create('health_specialties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 150);
                $table->string('code', 50);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_specialties_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_specialties_id_company_unique');
                $table->index(['company_id', 'name'], 'health_specialties_company_name_idx');
            });
        }

        if (! schemaTableExists('health_practitioners')) {
            Schema::create('health_practitioners', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Lien employé RH du tenant — pas de FK (pattern edu_teachers).
                $table->unsignedBigInteger('employee_id');
                $table->unsignedBigInteger('department_id')->nullable();
                // dr | pr | midwife | nurse | other — CHECK health_practitioners_title_check
                $table->string('title', 20)->default('dr');
                $table->string('license_number', 100)->nullable();
                // active | inactive — CHECK health_practitioners_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'employee_id'], 'health_practitioners_company_employee_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'health_practitioners_id_company_unique');
                $table->index(['company_id', 'status'], 'health_practitioners_company_status_idx');
                $table->index(['company_id', 'department_id'], 'health_practitioners_company_department_idx');

                // Cross-tenant impossible : la paire (department_id, company_id)
                // doit exister chez le MÊME tenant (nullable : praticien sans
                // service rattaché).
                $table->foreign(['department_id', 'company_id'], 'health_practitioners_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('health_practitioners');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_practitioners_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_practitioners\" ADD CONSTRAINT health_practitioners_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_practitioners_title_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_practitioners\" ADD CONSTRAINT health_practitioners_title_check "
                    ."CHECK (title IN ('dr','pr','midwife','nurse','other')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('health_practitioner_specialties')) {
            Schema::create('health_practitioner_specialties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('practitioner_id');
                $table->unsignedBigInteger('specialty_id');
                $table->timestamps();

                $table->unique(['company_id', 'practitioner_id', 'specialty_id'], 'health_pract_specialties_company_pract_specialty_unique');
                $table->index(['company_id', 'practitioner_id'], 'health_pract_specialties_company_pract_idx');
                $table->index(['company_id', 'specialty_id'], 'health_pract_specialties_company_specialty_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['practitioner_id', 'company_id'], 'health_pract_specialties_practitioner_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_practitioners')
                    ->cascadeOnDelete();
                $table->foreign(['specialty_id', 'company_id'], 'health_pract_specialties_specialty_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_specialties')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_practitioner_specialties');
        Schema::dropIfExists('health_practitioners');
        Schema::dropIfExists('health_specialties');
    }
};
