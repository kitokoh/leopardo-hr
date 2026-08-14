<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F-09/#1817 — Archivage automatique des bulletins PDF dans le Cabinet.
 *
 * Colonnes additives sur `cabinet_documents` :
 * - `document_type` : catégorie métier du document (`payslip`, …) pour
 *   retrouver le bulletin archivé depuis `/me/pay-slips/{slip}/document` ;
 * - `read_only` : verrou d'immutabilité — un bulletin archivé ne peut être
 *   ni renommé ni supprimé par l'employé (403).
 *
 * Migration additive et idempotente (rendue compatible avec les schémas
 * historiques déjà migrés, pattern Render/CI du dépôt).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cabinet_documents')) {
            return;
        }

        Schema::table('cabinet_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('cabinet_documents', 'read_only')) {
                $table->boolean('read_only')->default(false)->after('notes');
            }
            if (! Schema::hasColumn('cabinet_documents', 'document_type')) {
                $table->string('document_type', 30)->nullable()->after('read_only')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cabinet_documents')) {
            return;
        }

        Schema::table('cabinet_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('cabinet_documents', 'document_type')) {
                $table->dropColumn('document_type');
            }
            if (Schema::hasColumn('cabinet_documents', 'read_only')) {
                $table->dropColumn('read_only');
            }
        });
    }
};
