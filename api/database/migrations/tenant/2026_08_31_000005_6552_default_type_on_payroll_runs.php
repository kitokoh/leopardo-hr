<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Issue #6552 — `payroll_runs.type` n'avait pas de défaut : les inserts
 * sans `type` (tests, anciens flux) stockaient NULL, ce qui neutralisait
 * l'index unique partiel `WHERE type = 'standard'` (NULL ≠ 'standard').
 *
 * Correctif : défaut `'standard'` + backfill des NULL (additif, idempotent).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('payroll_runs')) {
            return;
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payroll_runs ALTER COLUMN type SET DEFAULT 'standard'");
            DB::statement("UPDATE payroll_runs SET type = 'standard' WHERE type IS NULL");
        } else {
            DB::statement('UPDATE payroll_runs SET type = \'standard\' WHERE type IS NULL');
        }
    }

    public function down(): void
    {
        // Le défaut est additif ; on ne retire pas la colonne.
    }
};
