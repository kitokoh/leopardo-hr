<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5819 (EDU-003).
 *
 * edu_teacher_subjects : affectation enseignant → matière pour une année
 * scolaire (tenant). Isolation stricte via `company_id` uuid NON nullable ;
 * une affectation est unique PAR TENANT (UNIQUE company_id+teacher_id+
 * subject_id+academic_year_id).
 *
 * Invariants portés par le schéma :
 *   - FK composites (teacher_id, company_id) → edu_teachers(id, company_id),
 *     (subject_id, company_id) → edu_subjects(id, company_id) et
 *     (academic_year_id, company_id) → edu_academic_years(id, company_id) :
 *     toute affectation croisant les tenants est STRUCTURELLEMENT impossible
 *     (violation FK) — un enseignant du tenant B ne peut JAMAIS être affecté
 *     à une matière du tenant A ;
 *   - UNIQUE(company_id, teacher_id, subject_id, academic_year_id) : pas de
 *     doublon d'affectation pour une année donnée ;
 *   - indexes tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_teacher_subjects')) {
            Schema::create('edu_teacher_subjects', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('teacher_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('academic_year_id');
                $table->timestamps();

                $table->unique(['company_id', 'teacher_id', 'subject_id', 'academic_year_id'], 'edu_teacher_subjects_company_teacher_subject_year_unique');
                $table->index(['company_id', 'teacher_id'], 'edu_teacher_subjects_company_teacher_idx');
                $table->index(['company_id', 'subject_id'], 'edu_teacher_subjects_company_subject_idx');
                $table->index(['company_id', 'academic_year_id'], 'edu_teacher_subjects_company_year_idx');

                // Cross-tenant impossible : chaque paire (X_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['teacher_id', 'company_id'], 'edu_teacher_subjects_teacher_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_teachers')
                    ->cascadeOnDelete();
                $table->foreign(['subject_id', 'company_id'], 'edu_teacher_subjects_subject_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_subjects')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_teacher_subjects_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_teacher_subjects');
    }
};
