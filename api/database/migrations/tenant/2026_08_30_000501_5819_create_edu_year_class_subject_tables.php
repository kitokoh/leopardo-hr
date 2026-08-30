<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5819 (EDU-003) — années scolaires, classes, matières, enseignants.
 *
 * `edu_academic_years` : période scolaire (début/fin cohérents — CHECK
 * end_date >= start_date), UNIQUE (company_id, code).
 * `edu_classes` : classe d'une année scolaire ; `campus_id` FK COMPOSITE
 * vers `edu_campuses` GARDÉE (table livrée par EDU-002, PR #5974) — si la
 * table n'existe pas encore à la migration, la FK est sautée et la
 * validation reste applicative (aucune collision de préfixe #1962).
 * `edu_subjects` : matières, UNIQUE (company_id, code).
 * `edu_teachers` : un enseignant = un employé du tenant (FK employees,
 * UNIQUE (company_id, employee_id)).
 * `edu_teacher_assignments` : affectation enseignant → (classe, matière,
 * année), UNIQUE (company_id, class_id, subject_id, academic_year_id).
 *
 * Index tenant-first partout ; PII minimale (aucun nom d'élève ici).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_academic_years')) {
            Schema::create('edu_academic_years', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'edu_academic_years_company_code_unique');
                $table->index(['company_id', 'status'], 'edu_academic_years_status_idx');
            });

            $this->addCheck('edu_academic_years', 'edu_academic_years_dates_check', 'end_date >= start_date');
            $this->addCheck('edu_academic_years', 'edu_academic_years_status_check', "status IN ('active', 'archived')");
        }

        if (! schemaTableExists('edu_classes')) {
            Schema::create('edu_classes', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('campus_id')->nullable()->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->string('grade_level', 40)->nullable();
                $table->unsignedSmallInteger('capacity')->nullable();
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->unique(['company_id', 'academic_year_id', 'code'], 'edu_classes_year_code_unique');
                $table->index(['company_id', 'academic_year_id'], 'edu_classes_year_idx');
            });

            $this->addCheck('edu_classes', 'edu_classes_status_check', "status IN ('active', 'archived')");
        }

        if (! schemaTableExists('edu_subjects')) {
            Schema::create('edu_subjects', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('name', 150);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'edu_subjects_company_code_unique');
            });
        }

        if (! schemaTableExists('edu_teachers')) {
            Schema::create('edu_teachers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('employee_id');
                $table->timestamps();

                $table->unique(['company_id', 'employee_id'], 'edu_teachers_company_employee_unique');
                $table->index(['company_id', 'employee_id'], 'edu_teachers_employee_idx');

                $table->foreign('employee_id', 'edu_teachers_employee_fk')
                    ->references('id')
                    ->on('employees')
                    ->cascadeOnDelete();
            });
        }

        if (! schemaTableExists('edu_teacher_assignments')) {
            Schema::create('edu_teacher_assignments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->unsignedBigInteger('teacher_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->string('status', 20)->default('active'); // active|archived
                $table->timestamps();

                $table->unique(
                    ['company_id', 'class_id', 'subject_id', 'academic_year_id'],
                    'edu_teacher_assignments_unique'
                );
            });

            $this->addCheck('edu_teacher_assignments', 'edu_teacher_assignments_status_check', "status IN ('active', 'archived')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_teacher_assignments');
        Schema::dropIfExists('edu_teachers');
        Schema::dropIfExists('edu_subjects');
        Schema::dropIfExists('edu_classes');
        Schema::dropIfExists('edu_academic_years');
    }

    private function addCheck(string $table, string $name, string $expression): void
    {
        $schema = resolveTableSchema($table);

        if ($schema === null) {
            return;
        }

        $exists = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        if ($exists === null) {
            DB::statement("ALTER TABLE {$schema}.{$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
        }
    }
};
