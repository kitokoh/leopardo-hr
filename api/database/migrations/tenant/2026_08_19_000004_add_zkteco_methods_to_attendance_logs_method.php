<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #5121 — `attendance_logs.method` doit accepter les méthodes kiosque.
 *
 * La sync kiosque (ZktecoIntegrationService) écrit `method => 'zkteco'`
 * (défaut legacy) et, depuis #5121, `method => fingerprint|face|card`
 * (méthode réelle du pointage). L'enum/contrainte actuelle n'autorise que
 * `mobile|qr|biometric|manual|geo_auto` → CHECK violation 23514 sur schéma
 * réel (masqué par CreatesMvpSchema en test) : AUCUN pointage kiosque ne peut
 * être inséré.
 *
 * Même pattern que `2026_08_09_000006_ensure_attendance_logs_method_geo_auto` :
 *  - ENUM PostgreSQL natif → ADD VALUE IF NOT EXISTS ×4 ;
 *  - VARCHAR + contrainte CHECK → reconstruction avec la liste étendue.
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
            return; // Colonne absente, rien à faire.
        }

        $values = ['zkteco', 'fingerprint', 'face', 'card'];

        $enumName = $result->udt_name;

        if ($result->data_type === 'USER-DEFINED') {
            // ENUM PostgreSQL natif — ajouter les valeurs si absentes.
            if (! is_string($enumName)) {
                return;
            }

            foreach ($values as $value) {
                DB::statement(
                    "ALTER TYPE {$enumName} ADD VALUE IF NOT EXISTS '{$value}'"
                );
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
                if (! is_object($check) || ! property_exists($check, 'definition') || ! property_exists($check, 'conname')) {
                    continue;
                }

                $definition = $check->definition;
                $conname = $check->conname;
                if (! is_string($definition) || ! is_string($conname)) {
                    continue;
                }

                if (str_contains($definition, "'zkteco'")) {
                    continue; // Déjà étendue.
                }

                DB::statement("ALTER TABLE attendance_logs DROP CONSTRAINT {$conname}");

                $allowed = ['mobile', 'qr', 'biometric', 'manual', 'geo_auto', ...$values];
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
            "COMMENT ON COLUMN attendance_logs.method IS 'mobile|qr|biometric|manual|geo_auto|zkteco|fingerprint|face|card. zkteco = kiosque (legacy), fingerprint/face/card = méthode réelle du pointage kiosque (#5121).'"
        );
    }

    public function down(): void
    {
        // Additif — aucune donnée supprimée ; pas de down (pattern geo_auto).
    }
};
