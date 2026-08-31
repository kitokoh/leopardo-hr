<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5819 (EDU-003) — années scolaires, classes, matières et enseignants.
 *
 * `edu_academic_years` : année scolaire d'un établissement (nom, bornes de
 * dates, statut). Période cohérente garantie par CHECK `start_date < end_date`
 * et unicité du nom par tenant.
 *
 * `edu_subjects` : matières enseignées (code unique par tenant, libellé,
 * coefficient par défaut, facultatif campus-scoped).
 *
 * `edu_classes` : classe rattachée à un campus + une année scolaire
 * (FK composites anti cross-tenant), avec un enseignant référent
 * (employee_id du même tenant, sans FK dure — pattern FuelStation) et une
 * capacité bornée (CHECK capacity > 0).
 *
 * `edu_teacher_subjects` : affectation d'un enseignant à une matière pour une
 * classe (pivot, UNIQUE par tenant) — historique conservé par soft-status.
 *
 * Toutes les tables : `company_id` uuid NON nullable, index tenant-first,
 * gardes schemaTableExists (idempotence #1613), CHECKs gardés pg_constraint.
 * Migration additive — rollback : down() supprime dans l'ordre inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_academic_years')) {
            Schema::create('edu_academic_years', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->string('name', 120);
                $table->date('start_date');
                $table->date('end_date');
                // active | closed — CHECK edu_academic_years_status_check
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'name'], 'edu_academic_years_company_name_unique');
                // Clé d'intégrité des FK composites (id, company_id) — requise
                // par les FK de edu_classes/edu_assessments/edu_report_cards/
                // edu_admissions/edu_course_slots (pattern edu_campuses).
                $table->unique(['id', 'company_id'], 'edu_academic_years_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_academic_years_company_status_idx');
                $table->index(['company_id', 'start_date'], 'edu_academic_years_company_start_idx');
                $table->index(['company_id', 'created_at'], 'edu_academic_years_company_created_idx');
            });

            $schema = resolveTableSchema('edu_academic_years');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_academic_years_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_academic_years\" ADD CONSTRAINT edu_academic_years_status_check "
                    ."CHECK (status IN ('active','closed')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_academic_years_period_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_academic_years\" ADD CONSTRAINT edu_academic_years_period_check "
                    .'CHECK (start_date < end_date); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_academic_years_id_company_unique') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_academic_years\" ADD CONSTRAINT edu_academic_years_id_company_unique "
                    ."UNIQUE (id, company_id); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_subjects')) {
            Schema::create('edu_subjects', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('campus_id')->nullable()->index();
                $table->string('code', 50);
                $table->string('name', 191);
                $table->decimal('default_coefficient', 5, 2)->default(1.00);
                // active | inactive | archived — CHECK edu_subjects_status_check
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'edu_subjects_company_code_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_subjects_id_company_unique');
                $table->index(['company_id', 'status'], 'edu_subjects_company_status_idx');
                $table->index(['company_id', 'name'], 'edu_subjects_company_name_idx');

                $table->foreign(['campus_id', 'company_id'], 'edu_subjects_campus_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_campuses')
                    ->nullOnDelete();
            });

            $schema = resolveTableSchema('edu_subjects');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_subjects_id_company_unique') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_subjects\" ADD CONSTRAINT edu_subjects_id_company_unique "
                    ."UNIQUE (id, company_id); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_classes')) {
            Schema::create('edu_classes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('campus_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->string('code', 50);
                $table->string('name', 191);
                $table->string('level', 50)->nullable();
                $table->unsignedInteger('teacher_id')->nullable()->index();
                $table->unsignedInteger('capacity')->nullable();
                // active | inactive | archived — CHECK edu_classes_status_check
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code', 'academic_year_id'], 'edu_classes_company_code_year_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_classes_id_company_unique');
                $table->index(['company_id', 'academic_year_id'], 'edu_classes_company_year_idx');
                $table->index(['company_id', 'status'], 'edu_classes_company_status_idx');

                $table->foreign(['campus_id', 'company_id'], 'edu_classes_campus_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_campuses')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_classes_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_classes');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_classes_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_classes\" ADD CONSTRAINT edu_classes_status_check "
                    ."CHECK (status IN ('active','inactive','archived')); END IF; END $$"
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_classes_capacity_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_classes\" ADD CONSTRAINT edu_classes_capacity_check "
                    .'CHECK (capacity IS NULL OR capacity > 0); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_classes_id_company_unique') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_classes\" ADD CONSTRAINT edu_classes_id_company_unique "
                    ."UNIQUE (id, company_id); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_teacher_subjects')) {
            Schema::create('edu_teacher_subjects', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->unsignedInteger('teacher_id')->index();
                // active | inactive — CHECK edu_teacher_subjects_status_check
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'class_id', 'subject_id', 'teacher_id'],
                    'edu_teacher_subjects_unique'
                );
                $table->index(['company_id', 'teacher_id'], 'edu_teacher_subjects_company_teacher_idx');
                $table->index(['company_id', 'subject_id'], 'edu_teacher_subjects_company_subject_idx');

                $table->foreign(['class_id', 'company_id'], 'edu_teacher_subjects_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['subject_id', 'company_id'], 'edu_teacher_subjects_subject_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_subjects')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_teacher_subjects');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_teacher_subjects_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_teacher_subjects\" ADD CONSTRAINT edu_teacher_subjects_status_check "
                    ."CHECK (status IN ('active','inactive')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_teacher_subjects');
        Schema::dropIfExists('edu_classes');
        Schema::dropIfExists('edu_subjects');
        Schema::dropIfExists('edu_academic_years');
    }
};
