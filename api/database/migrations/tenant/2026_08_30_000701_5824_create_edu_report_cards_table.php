<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5824 (EDU-008).
 *
 * edu_report_cards : bulletins de période d'un élève (tenant). Snapshot
 * REPRODUCTIBLE des moyennes par matière (`data` jsonb — pure fonction des
 * notes, aucun horodatage) ; `average_score` moyenne globale ; cycle de vie
 * borné draft → validated → published → archived.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites (id, company_id) ;
 *   - FK composites → edu_students / edu_classes / edu_academic_years : un
 *     bulletin référençant l'élève, la classe ou l'année d'un AUTRE tenant
 *     est structurellement impossible (violation FK) ;
 *   - UNIQUE(company_id, student_id, academic_year_id, period_label) : un
 *     seul bulletin par élève/période/année — la régénération passe par une
 *     MISE À JOUR du brouillon (idempotent), jamais un doublon ;
 *   - CHECK `status` (draft|validated|published|archived) — valeurs
 *     inconnues rejetées ;
 *   - CHECK `period_start < period_end` — période cohérente (pattern
 *     edu_academic_years_period_check, #5819) ;
 *   - indexes tenant-first pour listes (par classe, par élève).
 *
 * PII (classification `docs/architecture/EDUMANAGER_DONNEES.md`) : `data`
 * ne contient QUE des moyennes par matière — aucune donnée nominative dans
 * le snapshot (export PDF sans fuite hors tenant, RBAC EduReportCardPolicy).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente. Les tables parentes (edu_students
 * #5818, edu_classes/edu_academic_years #5819) sont livrées par des
 * migrations antérieures du même lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_report_cards')) {
            Schema::create('edu_report_cards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('academic_year_id');
                // Ex. 'S1', 'S2', 'Trimestre 1' — libellé libre du tenant.
                $table->string('period_label', 50);
                $table->date('period_start');
                $table->date('period_end');
                // Snapshot reproductible des moyennes par matière (jsonb).
                $table->jsonb('data');
                $table->decimal('average_score', 6, 2)->nullable();
                // draft | validated | published | archived — CHECK edu_report_cards_status_check
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestampTz('validated_at')->nullable();
                $table->timestampTz('published_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                // Un seul bulletin par élève / année scolaire / période.
                $table->unique(
                    ['company_id', 'student_id', 'academic_year_id', 'period_label'],
                    'edu_report_cards_company_student_year_period_unique'
                );
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_report_cards_id_company_unique');
                $table->index(['company_id', 'class_id'], 'edu_report_cards_company_class_idx');
                $table->index(['company_id', 'student_id'], 'edu_report_cards_company_student_idx');
                $table->index(['company_id', 'status'], 'edu_report_cards_company_status_idx');
                $table->index(['company_id', 'created_at'], 'edu_report_cards_company_created_idx');

                // Cross-tenant impossible : les paires (id, company_id) doivent
                // exister chez le MÊME tenant.
                $table->foreign(['student_id', 'company_id'], 'edu_report_cards_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['class_id', 'company_id'], 'edu_report_cards_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_report_cards_academic_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_report_cards');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_report_cards_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_report_cards\" ADD CONSTRAINT edu_report_cards_status_check "
                    ."CHECK (status IN ('draft','validated','published','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_report_cards_period_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_report_cards\" ADD CONSTRAINT edu_report_cards_period_check "
                    ."CHECK (period_start < period_end); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_report_cards');
    }
};
