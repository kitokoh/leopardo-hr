<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #1817 — Archivage automatique des bulletins PDF dans le Cabinet
 * employé après clôture (F-09/#1548).
 *
 * 1) Colonnes additives demandées par l'issue :
 *    - read_only      BOOLEAN DEFAULT false  → bulletin archivé non supprimable
 *    - document_type  VARCHAR(30) NULLABLE   → 'payslip', puis d'autres types
 *
 * 2) Correction de la tenancy de `cabinet_documents.company_id` :
 *    la colonne était un BIGINT hérité (la plupart des lignes à 0, cf.
 *    CabinetService::legacyCompanyKey) alors que tous les tenants sont des
 *    UUID (même convention que audit_logs, payroll_*, employees).
 *    Pour que l'archivage des bulletins soit réellement scopé au bon tenant
 *    (critère d'acceptation « CabinetDocument scopé au bon company_id »), la
 *    colonne est convertie en UUID nullable (comme employees.company_id) :
 *    les lignes legacy (0) passent d'abord à NULL, puis sont backfillées
 *    depuis le company_id de l'employé propriétaire du document.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('cabinet_documents')) {
            return;
        }

        $schema = resolveTableSchema('cabinet_documents');

        // 1) Colonnes additives (idempotent).
        Schema::table('cabinet_documents', function (Blueprint $table): void {
            if (! schemaHasColumn('cabinet_documents', 'read_only')) {
                $table->boolean('read_only')->default(false)->after('notes');
            }
            if (! schemaHasColumn('cabinet_documents', 'document_type')) {
                $table->string('document_type', 30)->nullable()->after('read_only');
            }
        });

        // 2) company_id BIGINT → UUID (tenancy correcte, idempotent).
        $column = DB::selectOne(
            "SELECT data_type
               FROM information_schema.columns
              WHERE table_schema = ?
                AND table_name = 'cabinet_documents'
                AND column_name = 'company_id'",
            [$schema]
        );

        if (is_object($column) && property_exists($column, 'data_type') && $column->data_type === 'bigint') {
            // Les lignes legacy (0) ne sont pas des UUID valides → NULL d'abord.
            DB::statement("ALTER TABLE \"{$schema}\".\"cabinet_documents\" ALTER COLUMN company_id DROP NOT NULL");
            DB::statement("UPDATE \"{$schema}\".\"cabinet_documents\" SET company_id = NULL WHERE company_id = 0");
            DB::statement("ALTER TABLE \"{$schema}\".\"cabinet_documents\" ALTER COLUMN company_id TYPE uuid USING company_id::text::uuid");

            // Backfill : chaque document retrouve le tenant de son employé.
            DB::statement(
                "UPDATE \"{$schema}\".\"cabinet_documents\" AS cd
                    SET company_id = e.company_id
                   FROM \"{$schema}\".\"employees\" AS e
                  WHERE e.id = cd.employee_id
                    AND cd.company_id IS NULL"
            );
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('cabinet_documents')) {
            return;
        }

        if (schemaHasColumn('cabinet_documents', 'document_type')) {
            Schema::table('cabinet_documents', function (Blueprint $table): void {
                $table->dropColumn('document_type');
            });
        }
        if (schemaHasColumn('cabinet_documents', 'read_only')) {
            Schema::table('cabinet_documents', function (Blueprint $table): void {
                $table->dropColumn('read_only');
            });
        }
        // down : on ne retype pas la colonne en BIGINT (destructif pour les
        // UUID existants) — la conversion UUID est unidirectionnelle.
    }
};
