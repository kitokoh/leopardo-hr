<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration SmartAttendance 05 — Ajout de la valeur 'geo_auto' dans attendance_logs.method
 *
 * Migration ADDITIVE uniquement — ne modifie aucune donnée existante.
 * Le champ `method` était VARCHAR(20) (migré depuis ENUM).
 * On s'assure simplement que la valeur 'geo_auto' est documentée et acceptée.
 *
 * Si la colonne est encore un ENUM PostgreSQL, on ajoute la valeur.
 * Si c'est déjà un VARCHAR, aucune action nécessaire.
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
        }
        // Si VARCHAR → aucune action requise, toute valeur est acceptée

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
