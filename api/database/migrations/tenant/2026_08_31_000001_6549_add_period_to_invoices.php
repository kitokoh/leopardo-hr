<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6549 — anti-doublon de facturation mensuelle.
 *
 * `billing:generate-invoices` pouvait émettre DEUX factures pour la même
 * période (double exécution / runs concurrents) : aucun contrôle
 * « facture déjà émise pour ce subscription/période ». La période n'était
 * même pas persistée.
 *
 * Correctif : colonne `period` (AAAA-MM, période de facturation) + index
 * unique (company_id, subscription_id, period) — verrou final en base,
 * le perdant d'une course reçoit 23505 et saute proprement.
 *
 * Migration additive + idempotente (garde #1962).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('invoices')) {
            return;
        }

        if (! schemaHasColumn('invoices', 'period')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->string('period', 7)->nullable()->after('subscription_id');
            });
        }

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS invoices_company_subscription_period_unique
            ON invoices (company_id, subscription_id, period)
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS invoices_company_subscription_period_unique');

        if (schemaTableExists('invoices') && schemaHasColumn('invoices', 'period')) {
            Schema::table('invoices', function (Blueprint $table): void {
                $table->dropColumn('period');
            });
        }
    }
};
