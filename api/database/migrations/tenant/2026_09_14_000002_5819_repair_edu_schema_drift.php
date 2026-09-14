<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5819 : réparation de la dérive de schéma.
 *
 * CONTEXTE (constaté le 2026-09-14 sur une base fraîche `leopardo:migrate
 * --fresh`) : le module EDU a été livré par PLUSIEURS jeux de migrations
 * déclarant les mêmes tables avec des colonnes divergentes :
 *
 *   - `2026_08_30_0002xx_5819_*` (série fine) ;
 *   - `2026_08_30_0007xx_5819_*` et `2026_08_30_0015xx_5819_*` (série groupée) ;
 *   - `2026_08_31_0002xx_5819_*` (révision la plus récente).
 *
 * Toutes les versions postérieures à la première sont neutralisées par la
 * garde d'idempotence `schemaTableExists()` — donc, contrairement à
 * l'intention (« la plus récente gagne »), c'est la PREMIÈRE migration qui
 * fige le schéma.
 *
 * Or les deux générations sont *à moitié câblées* dans le code : les modèles
 * Eloquent utilisent les noms de la génération réellement appliquée
 * (`edu_assessments.type`, `edu_report_cards.period`), tandis que des
 * services et une grande partie des tests attendent les noms de l'autre
 * génération (`assessment_type`, `period_label`). Aucune des deux n'est
 * complète, et la conséquence observable est :
 *
 *   - `POST /edu-manager/academic-years`      → 500 SQLSTATE 42703 ;
 *   - `POST /edu-manager/subjects|classes`    → 500 SQLSTATE 42703 ;
 *   - `POST /edu-manager/admissions`          → 500 (colonnes absentes) ;
 *   - `POST /edu-manager/report-cards/generate` → aurait 500 sur
 *     `period_label` (masqué par un 403, voir EduReportCardPolicy) ;
 *   - `tests/Feature/EduManager/` → 152 échecs sur `main`.
 *
 * DÉCISION (le 2026-09-14, audit parcours client « propriétaire d'école ») :
 * **adopter le sur-ensemble**. Cette migration est strictement additive et ne
 * renomme rien :
 *   1. elle AJOUTE les colonnes attendues par la génération la plus récente
 *      (donc par les services et les tests) — les colonnes historiques sont
 *      conservées, les modèles existants continuent de fonctionner ;
 *   2. elle ASSOUPLIT (`DROP NOT NULL`) uniquement les contraintes que la
 *      génération historique impose et que le code n'alimente jamais — sans
 *      quoi aucune ligne ne peut être insérée (constat : 25 violations
 *      `edu_admissions.applicant_name`, etc.).
 *
 * Elle est idempotente (chaque colonne gardée par `schemaHasColumn()`,
 * chaque assouplissement conditionné à `is_nullable = 'NO'`) et sûre en
 * production : schéma résolu par le search_path (helpers F-17
 * `resolveTableSchema`/`schemaHasColumn`/`schemaTableExists`, #1593/#1613).
 *
 * Ce que cette migration NE fait PAS (volontairement) : supprimer les
 * colonnes historiques, renommer `period`→`period_label`, ni consolider les
 * jeux de migrations dupliqués — ces décisions (destructives) appartiennent au
 * propriétaire et passent par une spec validée.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Colonnes manquantes (génération la plus récente) -------------

        $this->addColumns('edu_academic_years', [
            'notes' => static fn (Blueprint $t) => $t->text('notes')->nullable(),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        $this->addColumns('edu_classes', [
            'campus_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('campus_id')->nullable()->index(),
            'code' => static fn (Blueprint $t) => $t->string('code', 50)->nullable(),
            'level' => static fn (Blueprint $t) => $t->string('level', 50)->nullable(),
            'teacher_id' => static fn (Blueprint $t) => $t->unsignedInteger('teacher_id')->nullable()->index(),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        $this->addColumns('edu_subjects', [
            'campus_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('campus_id')->nullable()->index(),
            'default_coefficient' => static fn (Blueprint $t) => $t->decimal('default_coefficient', 5, 2)->default(1.00),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        $this->addColumns('edu_teacher_subjects', [
            'class_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('class_id')->nullable()->index(),
            'status' => static fn (Blueprint $t) => $t->string('status', 20)->default('active'),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        $this->addColumns('edu_attendance_corrections', [
            'attendance_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('attendance_id')->nullable()->index(),
        ]);

        $this->addColumns('edu_admissions', [
            'campus_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('campus_id')->nullable()->index(),
            'applicant_first_name' => static fn (Blueprint $t) => $t->string('applicant_first_name', 100)->nullable(),
            'applicant_last_name' => static fn (Blueprint $t) => $t->string('applicant_last_name', 100)->nullable(),
            'applicant_email' => static fn (Blueprint $t) => $t->string('applicant_email', 150)->nullable(),
            'applicant_phone' => static fn (Blueprint $t) => $t->string('applicant_phone', 30)->nullable(),
            'applicant_birth_date' => static fn (Blueprint $t) => $t->date('applicant_birth_date')->nullable(),
            'applied_at' => static fn (Blueprint $t) => $t->date('applied_at')->nullable(),
            'source' => static fn (Blueprint $t) => $t->string('source', 50)->nullable(),
            'external_id' => static fn (Blueprint $t) => $t->string('external_id', 100)->nullable(),
            'crm_contact_id' => static fn (Blueprint $t) => $t->string('crm_contact_id', 64)->nullable(),
            'consent_contact' => static fn (Blueprint $t) => $t->boolean('consent_contact')->default(false),
            'consented_at' => static fn (Blueprint $t) => $t->timestamp('consented_at')->nullable(),
            'converted_at' => static fn (Blueprint $t) => $t->timestamp('converted_at')->nullable(),
            'notes' => static fn (Blueprint $t) => $t->text('notes')->nullable(),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        // Évaluations : les tests/services attendent `assessment_type` et
        // `status` ; le modèle utilise `type`. Nullable : on n'impose rien aux
        // appelants qui continuent d'écrire `type` (fail-open volontaire, la
        // contrainte CHECK de la génération récente n'est pas rejouée ici).
        $this->addColumns('edu_assessments', [
            'assessment_type' => static fn (Blueprint $t) => $t->string('assessment_type', 30)->nullable(),
            'status' => static fn (Blueprint $t) => $t->string('status', 20)->default('draft'),
        ]);

        $this->addColumns('edu_grades', [
            'graded_at' => static fn (Blueprint $t) => $t->timestampTz('graded_at')->nullable(),
        ]);

        $this->addColumns('edu_grade_versions', [
            'changed_at' => static fn (Blueprint $t) => $t->timestampTz('changed_at')->nullable(),
            'previous_score' => static fn (Blueprint $t) => $t->decimal('previous_score', 8, 2)->nullable(),
            'new_score' => static fn (Blueprint $t) => $t->decimal('new_score', 8, 2)->nullable(),
            'previous_status' => static fn (Blueprint $t) => $t->string('previous_status', 20)->nullable(),
            'new_status' => static fn (Blueprint $t) => $t->string('new_status', 20)->nullable(),
            'reason' => static fn (Blueprint $t) => $t->string('reason', 500)->nullable(),
        ]);

        $this->addColumns('edu_report_cards', [
            'class_id' => static fn (Blueprint $t) => $t->unsignedBigInteger('class_id')->nullable()->index(),
            'period_label' => static fn (Blueprint $t) => $t->string('period_label', 50)->nullable(),
            'period_start' => static fn (Blueprint $t) => $t->date('period_start')->nullable(),
            'period_end' => static fn (Blueprint $t) => $t->date('period_end')->nullable(),
            'average_score' => static fn (Blueprint $t) => $t->decimal('average_score', 6, 2)->nullable(),
            'data' => static fn (Blueprint $t) => $t->jsonb('data')->nullable(),
            'created_by' => static fn (Blueprint $t) => $t->unsignedInteger('created_by')->nullable(),
        ]);

        // --- 2. Contraintes NOT NULL que le code ne peut pas satisfaire ------
        //
        // Colonnes héritées de la première génération, jamais alimentées par le
        // code courant : sans cet assouplissement, toute création échoue en
        // SQLSTATE 23502 (constaté : 25 × `edu_admissions.applicant_name`).
        $this->relaxNotNull('edu_admissions', [
            'applicant_name',
            'contact_reference',
            'consent_marketing',
            'consent_at',
        ]);

        $this->relaxNotNull('edu_teacher_subjects', ['academic_year_id']);
        $this->relaxNotNull('edu_attendance_corrections', ['attendance_record_id']);
        $this->relaxNotNull('edu_report_cards', ['period']);
    }

    public function down(): void
    {
        // Réparation additive : aucune colonne n'est retirée et aucune
        // contrainte n'est ré-appliquée (des données métier peuvent avoir été
        // écrites entre-temps). `down()` est volontairement un no-op.
    }

    /**
     * Ajoute les colonnes manquantes d'une table tenant, une seule fois.
     *
     * @param  array<string, callable(Blueprint): void>  $definitions
     */
    private function addColumns(string $table, array $definitions): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $missing = array_values(array_filter(
            array_keys($definitions),
            static fn (string $column): bool => ! schemaHasColumn($table, $column),
        ));

        if ($missing === []) {
            return;
        }

        Schema::table($this->qualified($table), function (Blueprint $blueprint) use ($definitions, $missing): void {
            foreach ($missing as $column) {
                $definitions[$column]($blueprint);
            }
        });
    }

    /**
     * Lève la contrainte NOT NULL d'une colonne (no-op si elle est déjà
     * nullable ou si la colonne n'existe pas).
     *
     * `ALTER COLUMN ... DROP NOT NULL` en SQL brut : `change()` exigerait de
     * re-déclarer le type de la colonne, et une erreur de type ici
     * convertirait la colonne (ex. `bigint` → `varchar`). Le SQL brut ne touche
     * que la nullabilité.
     *
     * @param  list<string>  $columns
     */
    private function relaxNotNull(string $table, array $columns): void
    {
        if (! schemaTableExists($table)) {
            return;
        }

        $schema = resolveTableSchema($table);

        if ($schema === null) {
            return;
        }

        foreach ($columns as $column) {
            if (! schemaHasColumn($table, $column)) {
                continue;
            }

            $row = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT is_nullable FROM information_schema.columns
                  WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                [$schema, $table, $column]
            );

            if ($row === null || (string) $row->is_nullable !== 'NO') {
                continue; // déjà nullable → no-op
            }

            \Illuminate\Support\Facades\DB::statement(sprintf(
                'ALTER TABLE %s.%s ALTER COLUMN %s DROP NOT NULL',
                $this->quote($schema),
                $this->quote($table),
                $this->quote($column),
            ));
        }
    }

    private function quote(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function qualified(string $table): string
    {
        $schema = resolveTableSchema($table);

        return $schema !== null ? $schema.'.'.$table : $table;
    }
};
