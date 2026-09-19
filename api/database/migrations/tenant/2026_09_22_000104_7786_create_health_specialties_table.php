<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_specialties : spécialités médicales du tenant. Référentiel seedé
 * à l'activation de la solution (HealthSpecialtySeederService, idempotent
 * — insertOrIgnore sur UNIQUE(company_id, code)) puis éditable par la
 * direction. `code` unique PAR TENANT ; statut borné (CHECK).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_specialties')) {
            Schema::create('health_specialties', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('code', 50);
                $table->string('name', 191);
                // active | inactive | archived — CHECK health_specialties_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_specialties_company_code_unique');
                // Clé d'intégrité des FK composites (pivot praticiens).
                $table->unique(['id', 'company_id'], 'health_specialties_id_company_unique');
                $table->index(['company_id', 'status'], 'health_specialties_company_status_idx');
                $table->index(['company_id', 'name'], 'health_specialties_company_name_idx');
            });

            $schema = resolveTableSchema('health_specialties');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_specialties_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_specialties\" ADD CONSTRAINT health_specialties_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_specialties');
    }
};
