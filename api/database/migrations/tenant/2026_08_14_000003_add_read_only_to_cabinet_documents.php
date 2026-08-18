<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1817 — Archivage automatique des bulletins PDF dans le Cabinet :
 * colonnes `read_only` (document non supprimable par l'employé) et
 * `document_type` (ex. 'payslip').
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('cabinet_documents')) {
            return;
        }

        Schema::table('cabinet_documents', function (Blueprint $table): void {
            if (! schemaHasColumn('cabinet_documents', 'read_only')) {
                $table->boolean('read_only')->default(false);
            }
            if (! schemaHasColumn('cabinet_documents', 'document_type')) {
                $table->string('document_type', 30)->nullable()->index();
            }
            // Lien fiable bulletin → document (notes est TEXT, pas JSONB).
            if (! schemaHasColumn('cabinet_documents', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! schemaTableExists('cabinet_documents')) {
            return;
        }

        Schema::table('cabinet_documents', function (Blueprint $table): void {
            if (schemaHasColumn('cabinet_documents', 'read_only')) {
                $table->dropColumn('read_only');
            }
            if (schemaHasColumn('cabinet_documents', 'document_type')) {
                $table->dropColumn('document_type');
            }
            if (schemaHasColumn('cabinet_documents', 'source_id')) {
                $table->dropColumn('source_id');
            }
        });
    }
};
