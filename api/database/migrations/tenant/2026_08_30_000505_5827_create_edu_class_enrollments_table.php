<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5827 (EDU-011) — inscriptions d'élèves dans les classes.
 *
 * `edu_class_enrollments` : inscription explicite d'un élève dans une classe
 * pour une année scolaire. UNIQUE (company_id, class_id, student_id) →
 * idempotence ; FK composites anti cross-tenant vers edu_classes et
 * edu_students ; statut borné (active|inactive|archived — le retrait passe
 * par soft-status, historique conservé, archivage RGPD). Alimente la
 * présence (EDU-005), l'espace enseignant (EDU-012) et les effectifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_class_enrollments')) {
            Schema::create('edu_class_enrollments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->timestamp('enrolled_at');
                // active | inactive | archived
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('enrolled_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'class_id', 'student_id'],
                    'edu_class_enrollments_unique'
                );
                $table->unique(['id', 'company_id'], 'edu_class_enrollments_id_company_unique');
                $table->index(['company_id', 'class_id', 'status'], 'edu_class_enrollments_company_class_status_idx');
                $table->index(['company_id', 'student_id'], 'edu_class_enrollments_company_student_idx');

                $table->foreign(['class_id', 'company_id'], 'edu_class_enrollments_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['student_id', 'company_id'], 'edu_class_enrollments_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_class_enrollments_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_class_enrollments');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_class_enrollments_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_class_enrollments\" ADD CONSTRAINT edu_class_enrollments_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_class_enrollments');
    }
};
