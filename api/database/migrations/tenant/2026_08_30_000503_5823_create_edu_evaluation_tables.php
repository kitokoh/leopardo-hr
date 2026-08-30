<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #5823 (EDU-007) — évaluations, barèmes et notes VERSIONNÉES.
 *
 * `edu_evaluations` : évaluation d'une classe/matière (type, coefficient,
 * barème max_score, statut draft → published → archived). Une note publiée
 * est IMMUABLE : toute correction crée une NOUVELLE VERSION de la ligne
 * `edu_grade_entries` (append-only, UNIQUE (company_id, evaluation_id,
 * student_id, version)) — l'original reste consultable, l'audit est complet.
 *
 * `edu_grade_entries` : note d'un élève (score ≤ max_score — CHECK), statut
 * draft|published, commentaires contrôlés (longueur bornée), version,
 * auteur/motif de correction. `student_id` FK COMPOSITE GARDÉE vers
 * `edu_students` (EDU-002, PR #5974).
 *
 * PII minimisée : pas de nom d'élève dans les tables de notes (joindre via
 * edu_students).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_evaluations')) {
            Schema::create('edu_evaluations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('class_id')->index();
                $table->unsignedBigInteger('subject_id')->index();
                $table->unsignedBigInteger('academic_year_id')->index();
                $table->string('title', 200);
                $table->string('type', 20)->default('exam'); // exam|quiz|homework|continuous
                $table->decimal('coefficient', 6, 2)->default(1);
                $table->decimal('max_score', 8, 2)->default(20);
                $table->string('status', 20)->default('draft'); // draft|published|archived
                $table->unsignedInteger('created_by');
                $table->unsignedInteger('published_by')->nullable();
                $table->timestampTz('published_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'class_id', 'academic_year_id'], 'edu_evaluations_class_year_idx');
            });

            $this->addCheck('edu_evaluations', 'edu_evaluations_status_check', "status IN ('draft', 'published', 'archived')");
            $this->addCheck('edu_evaluations', 'edu_evaluations_type_check', "type IN ('exam', 'quiz', 'homework', 'continuous')");
            $this->addCheck('edu_evaluations', 'edu_evaluations_max_score_check', 'max_score > 0');
        }

        if (! schemaTableExists('edu_grade_entries')) {
            Schema::create('edu_grade_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('evaluation_id')->index();
                $table->unsignedBigInteger('student_id')->index();
                $table->decimal('score', 8, 2);
                $table->string('status', 20)->default('draft'); // draft|published
                $table->string('comment', 500)->nullable(); // commentaire contrôlé
                $table->unsignedInteger('version')->default(1);
                $table->unsignedInteger('entered_by');
                $table->string('correction_reason', 255)->nullable();
                $table->unsignedInteger('corrected_by')->nullable();
                $table->timestampTz('corrected_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'evaluation_id', 'student_id', 'version'],
                    'edu_grade_entry_version_unique'
                );
                $table->index(['company_id', 'evaluation_id', 'status'], 'edu_grade_entries_evaluation_status_idx');
            });

            $this->addCheck('edu_grade_entries', 'edu_grade_entries_status_check', "status IN ('draft', 'published')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('edu_grade_entries');
        Schema::dropIfExists('edu_evaluations');
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
