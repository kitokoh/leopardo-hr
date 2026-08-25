<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #5435 — Rapprochement bancaire (Phase D) : relevés + lignes.
 *
 * `bank_statements` (relevé importé, soldes, statut, hash d'import) et
 * `bank_statement_lines` (lignes du relevé, statut de rapprochement,
 * paiement rapproché). Colonne de mapping CSV configurable ajoutée à
 * `accounting_settings`.
 *
 * Idempotence d'import : UNIQUE (company_id, statement_period,
 * import_reference) — le ré-import du même relevé est refusé (409).
 * Migration additive + idempotente (garde #1962/#5431), réf. issue dans le nom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('bank_statements')) {
            Schema::create('bank_statements', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('company_id')->index();
                $table->string('statement_period', 20);      // ex. "2026-08"
                $table->string('import_reference', 120);     // réf. externe du relevé
                $table->decimal('opening_balance', 14, 2)->nullable();
                $table->decimal('closing_balance', 14, 2)->nullable();
                $table->string('status', 20)->default('imported'); // imported|reconciling|reconciled
                $table->string('file_hash', 64)->nullable();
                $table->text('metadata')->nullable();        // cast encrypted:array
                $table->timestamps();

                $table->unique(['company_id', 'statement_period', 'import_reference'], 'bank_statements_import_unique');
            });
        }

        if (! schemaTableExists('bank_statement_lines')) {
            Schema::create('bank_statement_lines', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('statement_id')->index();
                $table->uuid('company_id')->index();
                $table->unsignedInteger('line_number');
                $table->date('line_date');
                $table->string('label', 255);
                $table->decimal('amount', 14, 2);            // signé (débit/crédit selon signe configuré)
                $table->string('external_reference', 120)->nullable();
                $table->string('category', 60)->nullable();
                $table->string('status', 20)->default('pending'); // pending|matched
                $table->uuid('matched_payment_id')->nullable()->index();
                $table->unsignedSmallInteger('confidence')->nullable(); // score 0-100
                $table->text('metadata')->nullable();
                $table->timestamps();

                $table->unique(['statement_id', 'line_number'], 'bank_statement_lines_number_unique');
            });
        }

        if (! schemaHasColumn('accounting_settings', 'bank_statement_mapping')) {
            Schema::table('accounting_settings', function (Blueprint $table): void {
                $table->json('bank_statement_mapping')->nullable()->after('document_language');
            });
        }

        // Références FK (PostgreSQL : ALTER ADD CONSTRAINT IF NOT EXISTS n'existe pas —
        // garde par information_schema pour rester idempotent).
        $fkExists = DB::selectOne(
            "SELECT 1 FROM information_schema.table_constraints
             WHERE constraint_name = 'bank_statement_lines_statement_fk'"
        );
        if ($fkExists === null) {
            DB::statement('ALTER TABLE bank_statement_lines
                ADD CONSTRAINT bank_statement_lines_statement_fk
                FOREIGN KEY (statement_id) REFERENCES bank_statements (id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statements');

        if (schemaHasColumn('accounting_settings', 'bank_statement_mapping')) {
            Schema::table('accounting_settings', function (Blueprint $table): void {
                $table->dropColumn('bank_statement_mapping');
            });
        }
    }
};
