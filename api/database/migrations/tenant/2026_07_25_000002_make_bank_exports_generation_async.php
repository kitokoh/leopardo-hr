<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PA2-PAY-014 — Bordereaux (bank exports) PDFs/files hors requete HTTP.
 *
 * `BankExportController::generate()` used to build the whole bank-transfer
 * file (SEPA XML / CCP Algerie / CPA / BNA / CSV) synchronously inside the
 * HTTP request, which does not scale for large payroll runs and blocks the
 * manager's mobile/web client until the whole file is rendered and written
 * to disk. This migration makes `bank_exports.status` support an async
 * pending/generating/failed lifecycle instead of just generated/sent/confirmed,
 * makes `file_path` nullable (no file exists yet while pending/generating),
 * and adds `error_message` for failed jobs — mirroring the existing
 * `payment_documents` pending -> generating -> available/failed pattern
 * (see 2026_06_01_000001_create_payment_documents_table.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('bank_exports');

        if (! schemaTableExists('bank_exports')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" DROP CONSTRAINT IF EXISTS bank_exports_status_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"bank_exports\" ADD CONSTRAINT bank_exports_status_check ".
                "CHECK (status IN ('pending', 'generating', 'generated', 'failed', 'sent', 'confirmed'))"
            );
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" ALTER COLUMN file_path DROP NOT NULL");
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" ALTER COLUMN status SET DEFAULT 'pending'");
        }
        // Note: SQLite (used by default in local/unit test runs) has no
        // NOT NULL/CHECK constraint enforcement issue here since
        // Schema::create() on that driver never enforced the original
        // `file_path` NOT NULL as strictly; no DBAL-dependent ->change()
        // is used so this migration works without doctrine/dbal installed.

        if (! schemaHasColumn('bank_exports', 'error_message')) {
            Schema::table("{$schema}.bank_exports", function (Blueprint $table) {
                $table->text('error_message')->nullable()->after('file_path');
            });
        }
    }

    public function down(): void
    {
        // Schéma résolu via le search_path (issue #1613).
        $schema = resolveTableSchema('bank_exports');

        if (! schemaTableExists('bank_exports')) {
            return;
        }

        if (schemaHasColumn('bank_exports', 'error_message')) {
            Schema::table("{$schema}.bank_exports", function (Blueprint $table) {
                $table->dropColumn('error_message');
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" DROP CONSTRAINT IF EXISTS bank_exports_status_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"bank_exports\" ADD CONSTRAINT bank_exports_status_check ".
                "CHECK (status IN ('generated', 'sent', 'confirmed'))"
            );
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" ALTER COLUMN status SET DEFAULT 'generated'");
        }
    }
};
