<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5824 (EDU-008) — bulletins et publication.
 *
 * `edu_report_cards` : bulletin d'un élève pour une période d'une année
 * scolaire. Statut borné (draft|validated|published) ; UNIQUE par tenant
 * (student_id, academic_year_id, period) → régénération idempotente.
 *
 * `edu_report_card_lines` : ligne par matière (moyenne calculée serveur,
 * coefficient, nombre d'évaluations prises en compte). Supprimées puis
 * recréées à chaque régénération (read model recalculable — pattern
 * FUEL-009 rapprochement).
 *
 * FK composites anti cross-tenant vers edu_students / edu_academic_years /
 * edu_subjects. company_id uuid NON nullable, index tenant-first, gardes
 * schemaTableExists, CHECK gardé pg_constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_report_cards')) {
            Schema::create('edu_report_cards', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                // term1 | term2 | term3 | final — CHECK edu_report_cards_period_check
                $table->string('period', 10);
                // draft | validated | published — CHECK edu_report_cards_status_check
                $table->string('status', 20)->default('draft');
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->unsignedInteger('validated_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'student_id', 'academic_year_id', 'period'],
                    'edu_report_cards_student_year_period_unique'
                );
                $table->index(
                    ['company_id', 'academic_year_id', 'period'],
                    'edu_report_cards_company_year_period_idx'
                );

                $table->foreign(['student_id', 'company_id'], 'edu_report_cards_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_report_cards_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_report_cards');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_report_cards_period_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_report_cards\" ADD CONSTRAINT edu_report_cards_period_check "
                    ."CHECK (period IN ('term1','term2','term3','final')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_report_cards_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_report_cards\" ADD CONSTRAINT edu_report_cards_status_check "
                    ."CHECK (status IN ('draft','validated','published')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_report_card_lines')) {
            Schema::create('edu_report_card_lines', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('report_card_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->decimal('average', 8, 2);
                $table->decimal('coefficient', 5, 2)->default(1.00);
                $table->unsignedInteger('assessment_count')->default(0);
                $table->timestamps();

                $table->unique(
                    ['company_id', 'report_card_id', 'subject_id'],
                    'edu_report_card_lines_card_subject_unique'
                );

                $table->foreign(['report_card_id', 'company_id'], 'edu_report_card_lines_card_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_report_cards')
                    ->cascadeOnDelete();
                $table->foreign(['subject_id', 'company_id'], 'edu_report_card_lines_subject_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_subjects')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_report_card_lines');
        Schema::dropIfExists('edu_report_cards');
    }
};
