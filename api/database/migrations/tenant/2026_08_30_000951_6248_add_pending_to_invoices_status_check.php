<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DEP-BC21 (#6248) — machine à états des factures : l'état `pending` manque
 * à la contrainte CHECK `invoices_status_check` créée par la migration
 * d'origine (enum Laravel). Sans cette migration, toute insertion d'une
 * facture en `pending` viole la contrainte (SQLSTATE 23514).
 */
return new class extends Migration
{
    private const CHECK = 'invoices_status_check';

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS '.self::CHECK);
        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT '.self::CHECK
            ." CHECK (status IN ('draft','sent','paid','overdue','cancelled','pending'))"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS '.self::CHECK);
        DB::statement(
            'ALTER TABLE invoices ADD CONSTRAINT '.self::CHECK
            ." CHECK (status IN ('draft','sent','paid','overdue','cancelled'))"
        );
    }
};
