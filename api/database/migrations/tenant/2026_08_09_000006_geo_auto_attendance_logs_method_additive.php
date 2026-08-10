<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec S-3 (#1663) — Durcissement paie : `attendance_logs.method` accepte 'geo_auto'.
 *
 * Migration ADDITIVE de rattrapage pour les environnements déjà migrés :
 * `2026_06_29_000205_extend_attendance_logs_method_for_geo_auto` a été
 * complétée rétroactivement (branche F-13b) pour gérer le cas VARCHAR + CHECK ;
 * les env qui ont exécuté l'ancienne version ne rejoueront pas ce code. Cette
 * migration ré-applique la même logique de façon idempotente :
 *   - ENUM PostgreSQL natif → `ADD VALUE IF NOT EXISTS 'geo_auto'` ;
 *   - VARCHAR + contrainte CHECK → extension de la liste autorisée ;
 *   - VARCHAR sans CHECK → aucune action (toute valeur acceptée).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (resolveTableSchema('attendance_logs') === null) {
            return; // Table absente dans ce contexte, skip.
        }

        $result = DB::selectOne(
            "SELECT data_type, udt_name
               FROM information_schema.columns
              WHERE table_name = 'attendance_logs'
                AND column_name = 'method'
                AND table_schema = current_schema()"
        );

        if ($result === null) {
            return; // Colonne absente — rien à faire.
        }

        if ($result->data_type === 'USER-DEFINED') {
            // ENUM PostgreSQL natif — ajouter la valeur (idempotent).
            $enumName = $result->udt_name;

            $exists = DB::selectOne(
                'SELECT 1 FROM pg_enum pe
                  JOIN pg_type pt ON pt.oid = pe.enumtypid
                 WHERE pt.typname = ?
                   AND pe.enumlabel = ?',
                [$enumName, 'geo_auto']
            );

            if ($exists === null) {
                DB::statement("ALTER TYPE {$enumName} ADD VALUE IF NOT EXISTS 'geo_auto'");
            }
        } else {
            // VARCHAR généré par Laravel (`$table->enum()` sur Postgres) avec une
            // contrainte CHECK — étendre la liste des valeurs autorisées.
            $checks = DB::select(
                "SELECT conname, pg_get_constraintdef(oid) AS definition
                   FROM pg_constraint
                  WHERE conrelid = 'attendance_logs'::regclass
                    AND contype = 'c'
                    AND pg_get_constraintdef(oid) LIKE '%method%'"
            );

            foreach ($checks as $check) {
                if (str_contains($check->definition, "'geo_auto'")) {
                    continue; // Déjà étendue.
                }

                DB::statement("ALTER TABLE attendance_logs DROP CONSTRAINT {$check->conname}");

                $allowed = ['mobile', 'qr', 'biometric', 'manual', 'geo_auto'];
                $list = implode(', ', array_map(
                    static fn (string $value): string => "'{$value}'::character varying",
                    $allowed
                ));

                DB::statement(
                    "ALTER TABLE attendance_logs ADD CONSTRAINT {$check->conname} "
                    ."CHECK ((method)::text = ANY ((ARRAY[{$list}])::text[]))"
                );
            }
        }

        DB::statement(
            "COMMENT ON COLUMN attendance_logs.method IS 'mobile|qr|biometric|manual|geo_auto. geo_auto = généré par SmartAttendance après approbation.'"
        );
    }

    public function down(): void
    {
        // PostgreSQL ne permet pas de supprimer une valeur d'ENUM — no-op.
    }
};
