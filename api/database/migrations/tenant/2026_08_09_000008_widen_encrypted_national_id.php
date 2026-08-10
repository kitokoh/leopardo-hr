<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec S-3/CI (#1663 + main rouge 2026-08-09) — `employees.national_id`
 * stocké chiffré (cast `encrypted`, AES-256-CBC base64 ≈ 230+ caractères)
 * mais colonne varchar(50) : tout insert d'un NID échoue sur les vraies
 * migrations (`value too long for character varying(50)`), y compris en
 * production. Migration additive : élargit la colonne à varchar(500).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('employees');

        if ($schema === null) {
            return; // Table absente dans ce contexte.
        }

        $column = DB::selectOne(
            'SELECT character_maximum_length
               FROM information_schema.columns
              WHERE table_schema = ?
                AND table_name = ?
                AND column_name = ?',
            [$schema, 'employees', 'national_id']
        );

        if ($column === null) {
            return; // Colonne absente — rien à élargir.
        }

        if ((int) $column->character_maximum_length >= 500) {
            return; // Déjà élargie.
        }

        DB::statement(
            "ALTER TABLE \"{$schema}\".\"employees\" ALTER COLUMN national_id TYPE varchar(500)"
        );
    }

    public function down(): void
    {
        // Rétrograde impossible sans perte de données (valeurs chiffrées) — additive assumée.
    }
};
