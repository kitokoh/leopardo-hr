<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5822 (EDU-006) — emplois du temps : créneaux de cours.
 *
 * `edu_course_slots` : créneau hebdomadaire récurrent (day_of_week 0-6,
 * start_time/end_time locaux du campus — timezone conservée sur le campus
 * parent), rattaché à une classe + une matière + un enseignant.
 *
 * - day_of_week borné par CHECK (0..6) ; end_time strictement après
 *   start_time (CHECK) — les créneaux ne chevauchent pas minuit (V0,
 *   documenté EDU-006).
 * - Conflits (enseignant ou classe sur le même créneau) contrôlés au niveau
 *   application (`EduCourseSlotService::assertNoConflict`).
 * - FK composites anti cross-tenant vers edu_classes / edu_subjects /
 *   edu_academic_years ; teacher_id = employé RH du même tenant (pas de FK
 *   dure — pattern FuelStation).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_course_slots')) {
            Schema::create('edu_course_slots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->unsignedInteger('teacher_id')->nullable()->index();
                $table->unsignedTinyInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('room', 100)->nullable();
                // active | cancelled — CHECK edu_course_slots_status_check
                $table->string('status', 20)->default('active');
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(
                    ['company_id', 'class_id', 'day_of_week'],
                    'edu_course_slots_company_class_day_idx'
                );
                $table->index(
                    ['company_id', 'teacher_id', 'day_of_week'],
                    'edu_course_slots_company_teacher_day_idx'
                );
                $table->index(['company_id', 'academic_year_id'], 'edu_course_slots_company_year_idx');

                $table->foreign(['class_id', 'company_id'], 'edu_course_slots_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['subject_id', 'company_id'], 'edu_course_slots_subject_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_subjects')
                    ->cascadeOnDelete();
                $table->foreign(['academic_year_id', 'company_id'], 'edu_course_slots_year_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_academic_years')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_course_slots');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_course_slots_day_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_course_slots\" ADD CONSTRAINT edu_course_slots_day_check "
                    .'CHECK (day_of_week BETWEEN 0 AND 6); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_course_slots_period_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_course_slots\" ADD CONSTRAINT edu_course_slots_period_check "
                    .'CHECK (end_time > start_time); END IF; END $$'
                );
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_course_slots_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_course_slots\" ADD CONSTRAINT edu_course_slots_status_check "
                    ."CHECK (status IN ('active','cancelled')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_course_slots');
    }
};
