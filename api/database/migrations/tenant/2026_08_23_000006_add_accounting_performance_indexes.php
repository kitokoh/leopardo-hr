<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5275 (perf/scale).
 *
 * Index complémentaires pour les workloads de charge du module :
 *   - accounting_documents (company_id, status, due_date) : liste par statut
 *     + requête « relances » (documents émis non soldés dont due_date ≤ J-7/15/30) ;
 *   - accounting_documents (company_id, issue_date) : journaux/rapports par
 *     période de facturation ;
 *   - accounting_payments (company_id, document_id, status) : liste des
 *     paiements filtrée document + statut (trésorerie).
 *
 * Migration additive et idempotente ; numérotation 000005 (aucune collision
 * avec 000001→000004 portées par les branches #5223/#5225/#5234/#5229).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table): void {
            if (! $this->indexExists('accounting_documents', 'accounting_documents_company_status_due_index')) {
                $table->index(['company_id', 'status', 'due_date'], 'accounting_documents_company_status_due_index');
            }
            if (! $this->indexExists('accounting_documents', 'accounting_documents_company_issue_index')) {
                $table->index(['company_id', 'issue_date'], 'accounting_documents_company_issue_index');
            }
        });

        Schema::table('accounting_payments', function (Blueprint $table): void {
            if (! $this->indexExists('accounting_payments', 'accounting_payments_company_document_status_index')) {
                $table->index(['company_id', 'document_id', 'status'], 'accounting_payments_company_document_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounting_documents', function (Blueprint $table): void {
            $table->dropIndex('accounting_documents_company_status_due_index');
            $table->dropIndex('accounting_documents_company_issue_index');
        });

        Schema::table('accounting_payments', function (Blueprint $table): void {
            $table->dropIndex('accounting_payments_company_document_status_index');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::hasIndex($table, $index);
    }
};
