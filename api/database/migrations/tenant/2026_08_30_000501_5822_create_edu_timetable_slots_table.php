<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EduManager — Issue #5822 (EDU-006).
 *
 * edu_timetable_slots : créneaux de l'emploi du temps d'un établissement
 * (tenant). Un créneau lie une classe, une matière et un enseignant sur un
 * créneau horaire d'un jour de la semaine (1 = lundi … 7 = dimanche).
 *
 * Invariants portés par le schéma :
 *   - `company_id` uuid NON nullable + UNIQUE(id, company_id) : clé
 *     d'intégrité des FK composites — une référence cross-tenant est une
 *     violation FK en base ;
 *   - FK composites (class_id, company_id) → edu_classes(id, company_id),
 *     (subject_id, company_id) → edu_subjects(id, company_id) et
 *     (teacher_id, company_id) → edu_teachers(id, company_id) : impossible
 *     de rattacher un créneau à une classe/matière/enseignant d'un AUTRE
 *     tenant (pattern FUEL-002/003/005) ;
 *   - UNIQUE(company_id, class_id, day_of_week, start_time) : pas de doublon
 *     exact de créneau (les chevauchements partiels sont contrôlés au niveau
 *     application — TimetableService::create, même classe OU même enseignant) ;
 *   - CHECK `day_of_week` entre 1 et 7 — jour inconnu rejeté ;
 *   - indexes tenant-first pour listes et calendriers (enseignant/jour,
 *     classe/jour).
 *
 * Dépendances EDU-003/004/005 (#5819/#5820/#5821) : les tables parentes
 * `edu_classes`, `edu_subjects` et `edu_teachers` sont livrées par d'autres
 * migrations du même lot. Les FK composites sont posées de façon GARDÉE
 * (pattern `addForeignKeyIfMissing` FUEL-005 #5799) : si la table parente
 * n'existe pas encore au moment où cette migration s'exécute, la contrainte
 * est ajoutée plus tard par une migration ultérieure — jamais un `up()`
 * en échec.
 *
 * Gardes F-17 (#1593/#1613) : schemaTableExists() + noms qualifiés ;
 * migration additive et idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('edu_timetable_slots')) {
            Schema::create('edu_timetable_slots', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('class_id');
                $table->unsignedBigInteger('subject_id');
                $table->unsignedBigInteger('teacher_id');
                // 1 = lundi … 7 = dimanche — CHECK edu_timetable_slots_day_of_week_check
                $table->smallInteger('day_of_week');
                $table->time('start_time');
                $table->time('end_time');
                $table->string('room', 80)->nullable();
                $table->timestamps();

                // Pas de doublon exact (même classe, même jour, même heure de début).
                $table->unique(
                    ['company_id', 'class_id', 'day_of_week', 'start_time'],
                    'edu_timetable_slots_company_class_day_start_unique'
                );
                // Clé d'intégrité des FK composites (id, company_id).
                $table->unique(['id', 'company_id'], 'edu_timetable_slots_id_company_unique');
                $table->index(['company_id', 'teacher_id', 'day_of_week'], 'edu_timetable_slots_company_teacher_day_idx');
                $table->index(['company_id', 'class_id', 'day_of_week'], 'edu_timetable_slots_company_class_day_idx');
            });

            $this->addCompositeForeignKeys();
            $this->addChecks();
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('edu_timetable_slots', 'edu_timetable_slots_teacher_company_fk');
        $this->dropForeignKeyIfExists('edu_timetable_slots', 'edu_timetable_slots_subject_company_fk');
        $this->dropForeignKeyIfExists('edu_timetable_slots', 'edu_timetable_slots_class_company_fk');

        Schema::dropIfExists('edu_timetable_slots');
    }

    /**
     * FK composites (id, company_id) — posées uniquement si la table parente
     * existe déjà (dépendances EDU-003/004/005, pattern FUEL-005 #5799).
     */
    private function addCompositeForeignKeys(): void
    {
        $this->addForeignKeyIfMissing(
            'edu_timetable_slots',
            'edu_timetable_slots_class_company_fk',
            ['class_id', 'company_id'],
            'edu_classes'
        );
        $this->addForeignKeyIfMissing(
            'edu_timetable_slots',
            'edu_timetable_slots_subject_company_fk',
            ['subject_id', 'company_id'],
            'edu_subjects'
        );
        $this->addForeignKeyIfMissing(
            'edu_timetable_slots',
            'edu_timetable_slots_teacher_company_fk',
            ['teacher_id', 'company_id'],
            'edu_teachers'
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
        $schema = resolveTableSchema('edu_timetable_slots');

        if ($schema === null) {
            return;
        }

        if ($this->constraintExists('edu_timetable_slots_day_of_week_check')) {
            return;
        }

        DB::statement(
            "ALTER TABLE \"{$schema}\".\"edu_timetable_slots\" ADD CONSTRAINT edu_timetable_slots_day_of_week_check "
            .'CHECK (day_of_week BETWEEN 1 AND 7)'
        );
    }

    private function constraintExists(string $name): bool
    {
        $row = DB::selectOne('SELECT 1 FROM pg_constraint WHERE conname = ?', [$name]);

        return $row !== null;
    }
};
