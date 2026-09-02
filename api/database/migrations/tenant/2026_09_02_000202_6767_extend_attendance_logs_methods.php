<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BIO-006 (#6767) — `attendance_logs.method` accepte `pin` et `manager`.
 *
 * Les flux kiosque multi-méthodes persistent la méthode RÉELLEMENT utilisée
 * (`fingerprint|face|card|pin|manager`). `pin` et `manager` sont de nouvelles
 * valeurs (méthodes de domaine ATT-002 #6761). Même pattern que
 * `2026_08_19_000004` : ENUM PostgreSQL natif → ADD VALUE ; VARCHAR + CHECK →
 * reconstruction de la contrainte avec la liste étendue.
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

        if (! is_object($result) || ! property_exists($result, 'data_type') || ! property_exists($result, 'udt_name')) {
            return;
        }

        $values = ['pin', 'manager'];
        $enumName = $result->udt_name;

        if ($result->data_type === 'USER-DEFINED') {
            if (! is_string($enumName)) {
                return;
            }

            foreach ($values as $value) {
                DB::statement("ALTER TYPE {$enumName} ADD VALUE IF NOT EXISTS '{$value}'");
            }
        } else {
            $checks = DB::select("
                SELECT conname, pg_get_constraintdef(oid) AS definition
                FROM pg_constraint
                WHERE conrelid = 'attendance_logs'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%method%'
            ");

            foreach ($checks as $check) {
                if (! is_object($check) || ! property_exists($check, 'definition') || ! property_exists($check, 'conname')) {
                    continue;
                }

                $definition = $check->definition;
                $conname = $check->conname;
                if (! is_string($definition) || ! is_string($conname)) {
                    continue;
                }

                if (str_contains($definition, "'pin'")) {
                    continue; // Déjà étendue.
                }

                DB::statement("ALTER TABLE attendance_logs DROP CONSTRAINT {$conname}");

                $allowed = ['mobile', 'qr', 'biometric', 'manual', 'geo_auto', 'zkteco', 'fingerprint', 'face', 'card', ...$values];
                $list = implode(', ', array_map(
                    static fn (string $value): string => "'{$value}'::character varying",
                    $allowed
                ));

                DB::statement(
                    "ALTER TABLE attendance_logs ADD CONSTRAINT {$conname} "
                    ."CHECK ((method)::text = ANY ((ARRAY[{$list}])::text[]))"
                );
            }
        }

        DB::statement(
            "COMMENT ON COLUMN attendance_logs.method IS 'mobile|qr|biometric|manual|geo_auto|zkteco|fingerprint|face|card|pin|manager. Méthode réellement utilisée (BIO-006 #6767).'"
        );
    }

    public function down(): void
    {
        // Additif — aucune donnée supprimée ; pas de down (pattern #5121).
    }
};
