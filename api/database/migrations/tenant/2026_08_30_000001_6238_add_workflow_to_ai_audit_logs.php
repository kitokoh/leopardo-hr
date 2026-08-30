<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BC-23-D10 (issue #6238) — colonne `workflow` sur `ai_audit_logs`.
 *
 * Distingue les appels chat direct des exécutions de workflow/agent dans
 * l'analytics AI (p95 par workflow, budgets de tokens) et prépare la
 * corrélation job_id (BC-23-D07). Additive, sans réécriture.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('ai_audit_logs') || schemaHasColumn('ai_audit_logs', 'workflow')) {
            return;
        }

        Schema::table('ai_audit_logs', function (Blueprint $table): void {
            $table->string('workflow', 100)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (schemaTableExists('ai_audit_logs') && schemaHasColumn('ai_audit_logs', 'workflow')) {
            Schema::table('ai_audit_logs', function (Blueprint $table): void {
                $table->dropColumn('workflow');
            });
        }
    }
};
