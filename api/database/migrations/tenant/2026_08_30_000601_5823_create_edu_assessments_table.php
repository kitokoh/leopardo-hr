<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5823 (EDU-007).
 *
 * edu_assessments : évaluations d'un établissement (tenant). Une évaluation
 * lie une classe, une matière et une année scolaire ; `max_score` est le
 * barème (positif, CHECK) et `coefficient` pondère la note dans le bulletin
 * (défaut 1.00). Cycle de vie : draft → published (notes verrouillées,
 * correction versionnée dans edu_grade_versions) → archived.
 *
 * PII (spec §6.3) : `title` reste un libellé court et borné (120
 * caractères) ; les notes (edu_grades.comment) sont bornées à 255 — jamais
 * de zone libre non bornée susceptible de porter des données sensibles.
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites des tables filles (notes) — une
 *     référence cross-tenant est une violation FK en base ;
 *   - FK composites (class_id, company_id) → edu_classes(id, company_id),
 *     (subject_id, company_id) → edu_subjects(id, company_id) et
 *     (academic_year_id, company_id) → edu_academic_years(id, company_id) :
 *     impossible de rattacher une évaluation à une classe/matière/année d'un
 *     AUTRE tenant (pattern FUEL-005 #5799) ;
 *   - CHECK `assessment_type` (exam|test|quiz|homework) — vocabulaire borné ;
 *   - CHECK `max_score` > 0 — barème positif exigé ;
 *   - CHECK `status` (draft|published|archived) — cycle de vie borné ;
 *   - UNIQUE(company_id, class_id, subject_id, title) — pas de doublon
 *     d'évaluation par classe/matière/titre ;
 *   - indexes tenant-first pour listes et dashboards.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_assessments')) {
            Schema::create('edu_assessments', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('academic_year_id');
                $table->string('title', 120);
                // exam | test | quiz | homework — CHECK edu_assessments_assessment_type_check
                $table->string('assessment_type', 30);
                // Barème — CHECK edu_assessments_max_score_check (max_score > 0)
                $table->decimal('max_score', 6, 2);
                $table->decimal('coefficient', 4, 2)->default(1.00);
                $table->date('assessment_date');
                // draft | published | archived — CHECK edu_assessments_status_check
                $table->string('status', 20)->default('draft');
                $table->timestampTz('published_at')->nullable();
                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();

                // Pas de doublon d'évaluation par classe/matière/titre.
                $table->unique(['company_id', 'class_id', 'subject_id', 'title'], 'edu_assessments_company_class_subject_title_unique');
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_assessments_id_company_unique');
                $table->index(['company_id', 'class_id', 'assessment_date'], 'edu_assessments_company_class_date_idx');
            });

            $this->addCompositeForeignKeys();
            $this->addChecks();
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('edu_assessments', 'edu_assessments_academic_year_company_fk');
        $this->dropForeignKeyIfExists('edu_assessments', 'edu_assessments_subject_company_fk');
        $this->dropForeignKeyIfExists('edu_assessments', 'edu_assessments_class_company_fk');

        Schema::dropIfExists('edu_assessments');
    }

    /**
     * FK composites (id, company_id) — posées uniquement si la table parente
     * existe déjà (dépendances EDU-003, pattern FUEL-005 #5799). Les tables
     * parentes edu_classes / edu_subjects / edu_academic_years sont livrées
     * par EDU-003 (#5819) dans le même lot.
     */
    private function addCompositeForeignKeys(): void
    {
        $this->addForeignKeyIfMissing(
            'edu_assessments',
            'edu_assessments_class_company_fk',
            ['class_id', 'company_id'],
            'edu_classes'
        );
        $this->addForeignKeyIfMissing(
            'edu_assessments',
            'edu_assessments_subject_company_fk',
            ['subject_id', 'company_id'],
            'edu_subjects'
        );
        $this->addForeignKeyIfMissing(
            'edu_assessments',
            'edu_assessments_academic_year_company_fk',
            ['academic_year_id', 'company_id'],
            'edu_academic_years'
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
        $schema = resolveTableSchema('edu_assessments');

        if ($schema === null) {
            return;
        }

        $checks = [
            'edu_assessments_assessment_type_check' => "assessment_type IN ('exam','test','quiz','homework')",
            'edu_assessments_max_score_check' => 'max_score > 0',
            'edu_assessments_status_check' => "status IN ('draft','published','archived')",
        ];

        foreach ($checks as $name => $expression) {
            if ($this->constraintExists($name)) {
                continue;
            }

            DB::statement(
                "ALTER TABLE \"{$schema}\".\"edu_assessments\" ADD CONSTRAINT {$name} CHECK ({$expression})"
            );
        }
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
