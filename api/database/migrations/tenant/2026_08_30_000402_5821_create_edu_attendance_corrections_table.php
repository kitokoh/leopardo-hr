<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5821 (EDU-005).
 *
 * edu_attendance_corrections : journal de VERSIONNAGE des corrections de
 * présence. Chaque modification d'un enregistrement écrit, AVANT la mise à
 * jour, une ligne (previous_status → new_status + motif) : jamais
 * d'écrasement silencieux, audit complet et rejouable.
 *
 * PII : `reason` (motif libre) peut contenir des informations personnelles —
 * jamais exposée hors tenant (RBAC manager uniquement).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + FK composite (attendance_record_id,
 *     company_id) → edu_attendance_records(id, company_id) : une correction
 *     cross-tenant est une violation FK en base ;
 *   - CHECK `new_status` borné (mêmes valeurs que edu_attendance_records) ;
 *   - `corrected_at` timestampTz (traçabilité horodatée, défaut = now) ;
 *   - index tenant-first (company_id, attendance_record_id) pour l'audit
 *     d'un enregistrement.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_attendance_corrections')) {
            Schema::create('edu_attendance_corrections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('attendance_record_id');
                $table->string('previous_status', 30);
                $table->string('new_status', 30);
                // PII potentielle (motif libre) — jamais hors tenant.
                $table->text('reason')->nullable();
                $table->unsignedInteger('corrected_by');
                $table->timestampTz('corrected_at')->useCurrent();
                $table->timestamps();

                $table->index(['company_id', 'attendance_record_id'], 'edu_attendance_corrections_company_record_idx');
                $table->index(['company_id', 'corrected_at'], 'edu_attendance_corrections_company_date_idx');

                // Cross-tenant impossible : la paire (attendance_record_id,
                // company_id) doit exister chez le MÊME tenant.
                $table->foreign(['attendance_record_id', 'company_id'], 'edu_attendance_corrections_record_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_attendance_records')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_attendance_corrections');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_attendance_corrections_new_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_attendance_corrections\" ADD CONSTRAINT edu_attendance_corrections_new_status_check "
                    ."CHECK (new_status IN ('present','absent','late','excused')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_attendance_corrections');
    }
};
