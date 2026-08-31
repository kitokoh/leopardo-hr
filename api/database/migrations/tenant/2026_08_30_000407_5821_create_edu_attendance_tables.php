<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5821 (EDU-005) — présence scolaire et corrections versionnées.
 *
 * `edu_attendances` : présence d'un élève pour une classe et une date.
 * Statut borné (present|absent|late|excused) ; UNIQUE par tenant
 * (class_id, student_id, attendance_date) → rejeu idempotent.
 * `recorded_by` : employé RH (enseignant) qui a saisi ; les exports sont
 * filtrés par classe (un enseignant ne voit que ses classes, Policy/scope).
 *
 * `edu_attendance_corrections` : journal de corrections VERSIONNÉ (jamais
 * d'UPDATE silencieux) — chaque correction enregistre l'ancien et le
 * nouveau statut, le motif, l'auteur et l'horodatage.
 *
 * FK composites anti cross-tenant vers edu_classes / edu_students.
 * company_id uuid NON nullable, index tenant-first, gardes schemaTableExists,
 * CHECKs gardés pg_constraint. Migration additive — rollback inverse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_attendances')) {
            Schema::create('edu_attendances', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->date('attendance_date');
                // present | absent | late | excused — CHECK edu_attendances_status_check
                $table->string('status', 20);
                $table->string('reason', 50)->nullable();
                $table->string('justification', 500)->nullable();
                $table->unsignedInteger('recorded_by')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'class_id', 'student_id', 'attendance_date'],
                    'edu_attendances_unique'
                );
                $table->index(['company_id', 'attendance_date'], 'edu_attendances_company_date_idx');
                $table->index(['company_id', 'student_id'], 'edu_attendances_company_student_idx');

                $table->foreign(['class_id', 'company_id'], 'edu_attendances_class_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_classes')
                    ->cascadeOnDelete();
                $table->foreign(['student_id', 'company_id'], 'edu_attendances_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_attendances');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_attendances_status_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_attendances\" ADD CONSTRAINT edu_attendances_status_check "
                    ."CHECK (status IN ('present','absent','late','excused')); END IF; END $$"
                );
            }
        }

        if (! schemaTableExists('edu_attendance_corrections')) {
            Schema::create('edu_attendance_corrections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('attendance_id')->index();
                $table->string('previous_status', 20);
                $table->string('new_status', 20);
                $table->string('reason', 500)->nullable();
                $table->unsignedInteger('corrected_by')->nullable();
                $table->timestamps();

                $table->index(
                    ['company_id', 'attendance_id', 'created_at'],
                    'edu_attendance_corrections_company_attendance_idx'
                );

                $table->foreign(['attendance_id', 'company_id'], 'edu_attendance_corrections_attendance_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_attendances')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_attendance_corrections');
        Schema::dropIfExists('edu_attendances');
    }
};
