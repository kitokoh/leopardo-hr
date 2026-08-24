<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5243 — Paie DZ : formats d'ordre de virement CNEP Banque + EDX.
 *
 * Le générateur (BankExportGenerator) et le contrôleur supportent déjà
 * cpa_dz/bna_dz depuis #2267, mais la contrainte CHECK `format` créée en
 * 2026_05_10_100001 ne les liste pas — tout export CPA/BNA (et désormais
 * CNEP/EDX) échouait en SQLSTATE 23514 à l'insertion de la ligne
 * `bank_exports`. Cette migration étend la contrainte à l'ensemble des
 * formats supportés par le générateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = resolveTableSchema('bank_exports');

        if (! schemaTableExists('bank_exports')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" DROP CONSTRAINT IF EXISTS bank_exports_format_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"bank_exports\" ADD CONSTRAINT bank_exports_format_check ".
                "CHECK (format IN ('sepa_xml', 'ccp_dz', 'cpa_dz', 'bna_dz', 'cnep_dz', 'edx_dz', 'virement_ma', 'csv_generic'))"
            );
        }
    }

    public function down(): void
    {
        $schema = resolveTableSchema('bank_exports');

        if (! schemaTableExists('bank_exports')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE \"{$schema}\".\"bank_exports\" DROP CONSTRAINT IF EXISTS bank_exports_format_check");
            DB::statement(
                "ALTER TABLE \"{$schema}\".\"bank_exports\" ADD CONSTRAINT bank_exports_format_check ".
                "CHECK (format IN ('sepa_xml', 'ccp_dz', 'virement_ma', 'csv_generic'))"
            );
        }
    }
};
