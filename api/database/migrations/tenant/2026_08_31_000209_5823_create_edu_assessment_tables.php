<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5823 (EDU-007) — évaluations et notes VERSIONNÉES.
 *
 * `edu_assessments` : évaluation d'une classe/matière (type borné, barème
 * `max_score` > 0 CHECK, coefficient > 0 CHECK, publication datée).
 *
 * `edu_grades` : note d'un élève pour une évaluation. Statut borné
 * (draft|published|corrected) ; UNIQUE (assessment_id, student_id) par
 * tenant → une seule note courante par élève et évaluation.
 *
 * `edu_grade_versions` : journal de versions — chaque correction ajoute une
 * version (score, commentaire, auteur, horodatage) SANS écraser l'historique
 * (audit trail complet ; `version` = numéro croissant par grade).
 *
 * FK composites anti cross-tenant vers edu_assessments / edu_students /
 * edu_classes / edu_subjects / edu_academic_years. company_id uuid NON
 * nullable, index tenant-first, gardes schemaTableExists, CHECKs gardés
 * pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_assessments')) {
            Schema::create('edu_assessments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->string('title', 191);
                // exam | quiz | homework | project — CHECK edu_assessments_type_check
                $table->string('type', 20);
                $table->decimal('coefficient', 5, 2)->default(1.00);
                $table->decimal('max_score', 8, 2)->default(20.00);
                $table->date('assessment_date')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                // UNIQUE(id, company_id) requis par les FK composites anti cross-tenant.
                $table->unique(['id', 'company_id'], 'edu_assessments_id_company_unique');

                $table->index(
                    ['company_id', 'class_id', 'assessment_date'],
                    'edu_assessments_company_class_date_idx'
                );
                $table->index(['company_id', 'subject_id'], 'edu_assessments_company_subject_idx');

                $table->foreign(['class_id', 'company_id'], 'edu_assessments_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['subject_id', 'company_id'], 'edu_assessments_subject_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_subjects')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_assessments_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_assessments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_assessments_type_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_assessments\" ADD CONSTRAINT edu_assessments_type_check "
                    ."CHECK (type IN ('exam','quiz','homework','project')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_assessments_max_score_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_assessments\" ADD CONSTRAINT edu_assessments_max_score_check "
                    .'CHECK (max_score > 0); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_assessments_coefficient_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_assessments\" ADD CONSTRAINT edu_assessments_coefficient_check "
                    .'CHECK (coefficient > 0); END IF; END $$'
                );
            }
        }

        if (! schemaTableExists('edu_grades')) {
            Schema::create('edu_grades', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('assessment_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->decimal('score', 8, 2);
                $table->string('comment', 500)->nullable();
                $table->unsignedInteger('graded_by')->nullable();
                // draft | published | corrected — CHECK edu_grades_status_check
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('version')->default(1);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                // UNIQUE(id, company_id) requis par les FK composites anti cross-tenant.
                $table->unique(['id', 'company_id'], 'edu_grades_id_company_unique');

                $table->unique(
                    ['company_id', 'assessment_id', 'student_id'],
                    'edu_grades_assessment_student_unique'
                );
                $table->index(['company_id', 'student_id'], 'edu_grades_company_student_idx');

                $table->foreign(['assessment_id', 'company_id'], 'edu_grades_assessment_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_assessments')
                    ->cascadeOnDelete();
                $table->foreign(['student_id', 'company_id'], 'edu_grades_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_grades');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_grades_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_grades\" ADD CONSTRAINT edu_grades_status_check "
                    ."CHECK (status IN ('draft','published','corrected')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_grade_versions')) {
            Schema::create('edu_grade_versions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('grade_id')->index();
                $table->unsignedInteger('version');
                $table->decimal('score', 8, 2);
                $table->string('comment', 500)->nullable();
                $table->unsignedInteger('changed_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'grade_id', 'version'],
                    'edu_grade_versions_grade_version_unique'
                );

                $table->foreign(['grade_id', 'company_id'], 'edu_grade_versions_grade_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_grades')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_grade_versions');
        Schema::dropIfExists('edu_grades');
        Schema::dropIfExists('edu_assessments');
    }
};
