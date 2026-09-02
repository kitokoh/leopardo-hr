<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5819 (EDU-003).
 *
 * edu_classes : classes d'un établissement (tenant), rattachées à une année
 * scolaire. Isolation stricte via `company_id` uuid NON nullable ; nom
 * unique PAR TENANT ET PAR ANNÉE (UNIQUE company_id+academic_year_id+name) ;
 * statut borné (CHECK).
 *
 * Invariants portés par le schéma :
 *   - FK composite (academic_year_id, company_id) → edu_academic_years
 *     (id, company_id) : une classe pointant l'année d'un AUTRE tenant est
 *     STRUCTURELLEMENT impossible (violation FK) ;
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
        if (! schemaTableExists('edu_classes')) {
            Schema::create('edu_classes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('academic_year_id');
                $table->string('name', 120);
                $table->string('grade_level', 50)->nullable();
                $table->integer('capacity')->nullable();
                // active | inactive | archived — CHECK edu_classes_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'academic_year_id', 'name'], 'edu_classes_company_year_name_unique');
                // Clé d'intégrité des FK composites (id, company_id) des tables filles.
                $table->unique(['id', 'company_id'], 'edu_classes_id_company_unique');
                $table->index(['company_id', 'academic_year_id'], 'edu_classes_company_year_idx');
                $table->index(['company_id', 'status'], 'edu_classes_company_status_idx');
                $table->index(['company_id', 'created_at'], 'edu_classes_company_created_idx');

                // Cross-tenant impossible : la paire (academic_year_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['academic_year_id', 'company_id'], 'edu_classes_academic_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_classes');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_classes_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_classes\" ADD CONSTRAINT edu_classes_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_classes');
    }
};
