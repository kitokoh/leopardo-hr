<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5833 (EDU-017) — import/export sécurisé.
 *
 * `edu_imports` : session d'import CSV (élèves, guardians, classes,
 * matières, notes). Cycle de vie : previewed → committed | cancelled |
 * failed. PREVIEW = parse + validation structurelle SANS écriture cible ;
 * COMMIT = persistance idempotente (statuts terminaux refusés), auditée.
 * Les lignes brutes sont conservées (rollback logique : re-commit à partir
 * du raw_rows, jamais d'écriture destructive).
 *
 * `edu_exports` : journal d'audit des exports CSV (qui, quoi, quand, combien)
 * — trace non altérable des accès aux données PII scolaires.
 *
 * company_id uuid NON nullable, index tenant-first, gardes schemaTableExists,
 * CHECKs gardés pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_imports')) {
            Schema::create('edu_imports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // students | guardians | classes | subjects | grades — CHECK edu_imports_entity_check
                $table->string('entity_type', 20);
                $table->string('filename', 255);
                // previewed | committed | cancelled | failed — CHECK edu_imports_status_check
                $table->string('status', 20)->default('previewed');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('valid_rows')->default(0);
                $table->unsignedInteger('error_rows')->default(0);
                $table->jsonb('columns')->nullable();
                $table->jsonb('preview_data')->nullable();
                $table->jsonb('errors')->nullable();
                $table->jsonb('raw_rows')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->unsignedInteger('committed_by')->nullable();
                $table->timestamp('committed_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'status'], 'edu_imports_company_status_idx');
                $table->index(['company_id', 'created_at'], 'edu_imports_company_created_idx');
            });

            $schema = resolveTableSchema('edu_imports');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_imports_entity_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_imports\" ADD CONSTRAINT edu_imports_entity_check "
                    ."CHECK (entity_type IN ('students','guardians','classes','subjects','grades')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_imports_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_imports\" ADD CONSTRAINT edu_imports_status_check "
                    ."CHECK (status IN ('previewed','committed','cancelled','failed')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_exports')) {
            Schema::create('edu_exports', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                // students | presence | grades — CHECK edu_exports_kind_check
                $table->string('kind', 20);
                $table->string('filename', 255);
                $table->unsignedInteger('record_count')->default(0);
                $table->unsignedInteger('exported_by')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'created_at'], 'edu_exports_company_created_idx');
            });

            $schema = resolveTableSchema('edu_exports');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_exports_kind_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_exports\" ADD CONSTRAINT edu_exports_kind_check "
                    ."CHECK (kind IN ('students','presence','grades')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_exports');
        Schema::dropIfExists('edu_imports');
    }
};
