<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HealthManager — Issue #7786 (HC-002, BC-30).
 *
 * health_rooms : salles d'un établissement de santé, tenant-scoped.
 * Une salle appartient à UN service médical (FK composite → cross-tenant
 * impossible : la paire (department_id, company_id) doit exister chez le
 * MÊME tenant). `code` unique PAR TENANT ; statut borné (CHECK).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Une table = une migration (#7452).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('health_rooms')) {
            Schema::create('health_rooms', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('department_id');
                $table->string('code', 50);
                $table->string('name', 191);
                // consultation | hospitalization | surgery | emergency | other
                $table->string('room_type', 30)->default('other');
                // active | inactive | archived — CHECK health_rooms_status_check
                $table->string('status', 20)->default('active');
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'health_rooms_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id) — lits.
                $table->unique(['id', 'company_id'], 'health_rooms_id_company_unique');
                $table->index(['company_id', 'department_id'], 'health_rooms_company_department_idx');
                $table->index(['company_id', 'status'], 'health_rooms_company_status_idx');

                // Cross-tenant impossible : le service référencé doit exister
                // chez le MÊME tenant (pattern edu_teacher_subjects).
                $table->foreign(['department_id', 'company_id'], 'health_rooms_department_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('health_departments')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('health_rooms');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_rooms_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_rooms\" ADD CONSTRAINT health_rooms_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'health_rooms_type_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"health_rooms\" ADD CONSTRAINT health_rooms_type_check "
                    ."CHECK (room_type IN ('consultation','hospitalization','surgery','emergency','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_rooms');
    }
};
