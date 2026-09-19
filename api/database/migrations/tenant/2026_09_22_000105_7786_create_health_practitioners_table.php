<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_practitioners : praticiens d'un établissement de santé (tenant).
 * `employee_id` pointe un employé RH du tenant SANS créer de FK : le lien
 * reste découplé (pattern `edu_teachers.employee_id`, #5819) — un praticien
 * peut exister sans dossier RH complet, et le référentiel RH n'est pas
 * couplé aux tables santé. UNIQUE(company_id, employee_id) : un employé RH
 * ne peut être praticien qu'UNE fois par tenant (NULL multiples autorisés
 * par PostgreSQL). Statut borné (CHECK).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_practitioners')) {
            Schema::create('health_practitioners', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // Lien employé RH du tenant — pas de FK (pattern edu_teachers).
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->string('display_name', 191);
                // Donnée professionnelle de santé (n° d'ordre / RPPS).
                $table->string('license_number', 100)->nullable();
                $table->string('title', 50)->nullable();
                // active | inactive | archived — CHECK health_practitioners_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'employee_id'], 'health_practitioners_company_employee_unique');
                // Clé d'intégrité des FK composites (pivot spécialités).
                $table->unique(['id', 'company_id'], 'health_practitioners_id_company_unique');
                $table->index(['company_id', 'status'], 'health_practitioners_company_status_idx');
                $table->index(['company_id', 'display_name'], 'health_practitioners_company_name_idx');
            });

            $schema = resolveTableSchema('health_practitioners');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_practitioners_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_practitioners\" ADD CONSTRAINT health_practitioners_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_practitioners');
    }
};
