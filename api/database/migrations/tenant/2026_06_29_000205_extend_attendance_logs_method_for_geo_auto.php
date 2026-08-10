<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration SmartAttendance 05 — Ajout de la valeur 'geo_auto' dans attendance_logs.method
 *
 * ⚠️ Spec S-3 (#1663) : ce fichier a été complété rétroactivement (branche
 * F-13b) ; les environnements déjà migrés ne rejoueront pas ce code. La
 * migration additive de rattrapage vit dans
 * `2026_08_09_000006_geo_auto_attendance_logs_method_additive.php` — ne pas
 * supprimer l'une sans l'autre.
 *
 * Migration ADDITIVE uniquement — ne modifie aucune donnée existante.
 * Le champ `method` était VARCHAR(20) (migré depuis ENUM).
 * On s'assure simplement que la valeur 'geo_auto' est documentée et acceptée.
 *
 * Si la colonne est encore un ENUM PostgreSQL, on ajoute la valeur.
 * Si c'est un VARCHAR + contrainte CHECK (cas Laravel `$table->enum()` sur
 * Postgres), on étend la contrainte CHECK avec 'geo_auto'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Vérifier le type réel de la colonne
        $result = DB::selectOne("
            SELECT data_type, udt_name
            FROM information_schema.columns
            WHERE table_name = 'attendance_logs'
              AND column_name = 'method'
              AND table_schema = current_schema()
        ");

        if (! $result) {
            return; // Table absente dans ce contexte, skip
        }

        if ($result->data_type === 'USER-DEFINED') {
            // C'est un ENUM PostgreSQL natif — ajouter la valeur
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
            // VARCHAR généré par Laravel (`$table->enum()` sur Postgres) avec une
            // contrainte CHECK — étendre la liste des valeurs autorisées.
            $checks = DB::select("
                SELECT conname, pg_get_constraintdef(oid) AS definition
                FROM pg_constraint
                WHERE conrelid = 'attendance_logs'::regclass
                  AND contype = 'c'
                  AND pg_get_constraintdef(oid) LIKE '%method%'
            ");

            foreach ($checks as $check) {
                if (str_contains($check->definition, "'geo_auto'")) {
                    continue; // Déjà étendue
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
        // PostgreSQL ne permet pas de supprimer une valeur d'ENUM
        // Aucune rollback nécessaire pour un VARCHAR
    }
};
