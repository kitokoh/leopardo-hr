<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * S-3 (#1663) — Ré-application ADDITIVE du fix `geo_auto` sur
 * `attendance_logs.method`.
 *
 * Contexte : la migration `2026_06_29_000205_extend_attendance_logs_method_for_geo_auto`
 * a été modifiée RÉTROACTIVEMENT (revue PR #1644, F-13b) pour devenir
 * additive/idempotente. Les environnements déjà migrés avec l'ancienne
 * version ne rejoueront JAMAIS le nouveau code de 000205 — cette migration
 * ré-applique la même logique idempotente pour eux.
 *
 * No-op sur un env neuf (000205 a déjà fait le travail) et sur un env déjà
 * corrigé (gardes d'existence).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('attendance_logs')) {
            return;
        }

        $result = DB::selectOne("
            SELECT data_type, udt_name
            FROM information_schema.columns
            WHERE table_name = 'attendance_logs'
              AND column_name = 'method'
              AND table_schema = current_schema()
        ");

        if (! $result) {
            return; // Colonne absente, rien à faire.
        }

        if ($result->data_type === 'USER-DEFINED') {
            // ENUM PostgreSQL natif — ajouter la valeur si absente.
            $enumName = $result->udt_name;

            $exists = DB::selectOne("
                SELECT 1 FROM pg_enum pe
                JOIN pg_type pt ON pt.oid = pe.enumtypid
                WHERE pt.typname = ?
                  AND pe.enumlabel = 'geo_auto'
            ", [$enumName]);

            if (! $exists) {
                DB::statement("ALTER TYPE {$enumName} ADD VALUE IF NOT EXISTS 'geo_auto'");
            }
        } else {
            // VARCHAR généré par Laravel + contrainte CHECK — étendre la liste.
            $checks = DB::select("
                SELECT conname, pg_get_constraintdef(oid) AS definition
                FROM pg_constraint
                WHERE conrelid = 'attendance_logs'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%method%'
            ");

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
        // PostgreSQL ne permet pas de retirer une valeur d'ENUM — no-op assumé
        // (identique à 000205).
    }
};
