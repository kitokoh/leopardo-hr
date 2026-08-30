<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5823 (EDU-007).
 *
 * edu_grades : notes d'une évaluation (tenant). Une note = (évaluation,
 * élève) ; `score` est borné (>= 0, CHECK) et `comment` est une zone
 * BORNÉE à 255 caractères (PII minimisée, spec §6.3 — jamais de commentaire
 * libre non borné). Statut : draft (modifiable) → published (immuable hors
 * correction auditable versionnée dans edu_grade_versions).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites de la table de versionnage ;
 *   - FK composite (assessment_id, company_id) → edu_assessments(id,
 *     company_id) : une note rattachée à l'évaluation d'un AUTRE tenant est
 *     une violation FK en base ;
 *   - FK composite (student_id, company_id) → edu_students(id, company_id) :
 *     une note pour l'élève d'un autre tenant est STRUCTURELLEMENT
 *     impossible (pattern FUEL-005 #5799) ;
 *   - UNIQUE(company_id, assessment_id, student_id) : une seule note par
 *     élève et par évaluation (idempotence) ;
 *   - CHECK `score` >= 0 — note négative rejetée ;
 *   - CHECK `status` (draft|published) — cycle de vie borné ;
 *   - indexes tenant-first pour listes, dashboards et exports filtrés.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_grades')) {
            Schema::create('edu_grades', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('assessment_id');
                $table->unsignedBigInteger('student_id');
                // Barème validé côté serveur (score <= max_score de l'évaluation) —
                // CHECK edu_grades_score_check (score >= 0)
                $table->decimal('score', 6, 2);
                // PII minimisée : zone bornée (255), pas de texte libre non borné.
                $table->string('comment', 255)->nullable();
                // draft | published — CHECK edu_grades_status_check
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('graded_by')->nullable();
                $table->timestampTz('graded_at')->nullable();
                $table->timestamps();

                // Une seule note par élève et par évaluation (idempotence).
                $table->unique(['company_id', 'assessment_id', 'student_id'], 'edu_grades_company_assessment_student_unique');
                // Clé d'intégrité des FK composites de edu_grade_versions.
                $table->unique(['id', 'company_id'], 'edu_grades_id_company_unique');
                $table->index(['company_id', 'student_id'], 'edu_grades_company_student_idx');
            });

            $this->addCompositeForeignKeys();
            $this->addChecks();
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('edu_grades', 'edu_grades_student_company_fk');
        $this->dropForeignKeyIfExists('edu_grades', 'edu_grades_assessment_company_fk');

        Schema::dropIfExists('edu_grades');
    }

    /**
     * FK composites (id, company_id) — posées uniquement si la table parente
     * existe déjà. Les tables parentes edu_assessments (migration 000601 du
     * même lot) et edu_students (EDU-002, #5818) sont déjà livrées.
     */
    private function addCompositeForeignKeys(): void
    {
        $this->addForeignKeyIfMissing(
            'edu_grades',
            'edu_grades_assessment_company_fk',
            ['assessment_id', 'company_id'],
            'edu_assessments'
        );
        $this->addForeignKeyIfMissing(
            'edu_grades',
            'edu_grades_student_company_fk',
            ['student_id', 'company_id'],
            'edu_students'
        );
    }

    private function addForeignKeyIfMissing(
        string $table,
        string $constraint,
        array $columns,
        string $references,
    ): void {
        if (! schemaTableExists($table) || ! schemaTableExists($references)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_schema = ANY (current_schemas(false))',
            [$constraint]
        );

        if ($exists === null) {
            DB::statement(
                "ALTER TABLE {$table} ADD CONSTRAINT {$constraint}
                 FOREIGN KEY (".implode(', ', $columns).')
                 REFERENCES '.$references.' (id, company_id) ON DELETE CASCADE'
            );
        }
    }

    private function dropForeignKeyIfExists(string $table, string $constraint): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $exists = DB::selectOne(
            'SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = ? AND table_schema = ANY (current_schemas(false))',
            [$constraint]
        );

        if ($exists === null) {
            return;
        }

        $schema = resolveTableSchema($table);

        if ($schema !== null) {
            DB::statement("ALTER TABLE {$schema}.{$table} DROP CONSTRAINT {$constraint}");
        }
    }

    private function addChecks(): void
    {
        $schema = resolveTableSchema('edu_grades');

        if ($schema === null) {
            return;
        }

        $checks = [
            'edu_grades_score_check' => 'score >= 0',
            'edu_grades_status_check' => "status IN ('draft','published')",
        ];

        foreach ($checks as $name => $expression) {
            if ($this->constraintExists($name)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE \"{$schema}\".\"edu_grades\" ADD CONSTRAINT {$name} CHECK ({$expression})"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
