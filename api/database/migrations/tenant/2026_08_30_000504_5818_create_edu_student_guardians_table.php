<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5818 (EDU-002).
 *
 * edu_student_guardians : relations AUTORISÉES entre responsables légaux et
 * élèves. Un responsable ne voit QUE les élèves auxquels il est explicitement
 * rattaché (acceptance « tests guardian non autorisé ») ; les droits sont
 * fins : `can_view_grades`, `can_receive_notifications`.
 *
 * Invariants portés par le schéma :
 *   - FK composites (student_id, company_id) → edu_students(id, company_id)
 *     et (guardian_id, company_id) → edu_guardians(id, company_id) : une
 *     relation cross-tenant est STRUCTURELLEMENT impossible (violation FK) ;
 *   - UNIQUE(company_id, student_id, guardian_id) : pas de doublon ;
 *   - CHECK `relationship_code` borné (mêmes valeurs que edu_guardians).
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_student_guardians')) {
            Schema::create('edu_student_guardians', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('student_id');
                $table->unsignedBigInteger('guardian_id');
                // parent | guardian | other — CHECK edu_student_guardians_relationship_check
                $table->string('relationship_code', 30)->default('parent');
                $table->boolean('can_view_grades')->default(false);
                $table->boolean('can_receive_notifications')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'student_id', 'guardian_id'], 'edu_student_guardians_company_student_guardian_unique');
                $table->index(['company_id', 'student_id'], 'edu_student_guardians_company_student_idx');
                $table->index(['company_id', 'guardian_id'], 'edu_student_guardians_company_guardian_idx');
                $table->index(['company_id', 'student_id', 'can_view_grades'], 'edu_student_guardians_company_student_grades_idx');

                // Cross-tenant impossible : la paire (student_id, company_id)
                // doit exister chez le MÊME tenant.
                $table->foreign(['student_id', 'company_id'], 'edu_student_guardians_student_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_students')
                    ->cascadeOnDelete();
                $table->foreign(['guardian_id', 'company_id'], 'edu_student_guardians_guardian_company_fk')
                    ->references(['id', 'company_id'])
                    ->on('edu_guardians')
                    ->cascadeOnDelete();
            });

            $schema = resolveTableSchema('edu_student_guardians');
            if ($schema !== null) {
                DB::statement(
                    "DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'edu_student_guardians_relationship_check') "
                    ."THEN ALTER TABLE \"{$schema}\".\"edu_student_guardians\" ADD CONSTRAINT edu_student_guardians_relationship_check "
                    ."CHECK (relationship_code IN ('parent','guardian','other')); END IF; END $$"
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_student_guardians');
    }
};
