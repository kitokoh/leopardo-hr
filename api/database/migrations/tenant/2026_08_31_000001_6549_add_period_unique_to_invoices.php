<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #6549 — anti-doublon de facturation par période sur `invoices`.
 *
 * Ajoute la colonne `period` (YYYY-MM) + un index unique
 * (company_id, subscription_id, period) : la commande
 * `billing:generate-invoices` ne peut plus émettre deux factures pour la
 * même période/abonnement, même sous double exécution ou runs concurrents
 * (garde dure en base ; la garde douce applicative reste dans la commande).
 * Additive : les factures historiques gardent `period` NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('invoices') || schemaHasColumn('invoices', 'period')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('period', 7)->nullable()->after('number');
            $table->unique(['company_id', 'subscription_id', 'period'], 'invoices_company_subscription_period_unique');
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('invoices') || ! schemaHasColumn('invoices', 'period')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_company_subscription_period_unique');
            $table->dropColumn('period');
        });
    }
};
