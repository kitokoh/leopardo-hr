<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9369.
 *
 * La saisie d'écriture libre (journal d'opérations diverses) bute sur la
 * contrainte d'idempotence du posting dérivé :
 *
 * ```sql
 * UNIQUE (company_id, source_type, source_id, account_code)
 * ```
 *
 * Elle garantit qu'un re-posting de facture ne duplique pas ses lignes — mais
 * elle interdit aussi **deux lignes sur le même compte dans une même écriture
 * manuelle**, cas pourtant courant (deux charges imputées au même compte 613,
 * ou un virement ventilé sur le même compte de tiers).
 *
 * Cette migration introduit donc un discriminant `line_no` (défaut 1) : les
 * écritures dérivées gardent exactement le comportement d'avant (line_no = 1,
 * unicité par compte, re-posting idempotent), les écritures manuelles
 * numérotent leurs lignes. Aucune ligne existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_journal_entries')) {
            return;
        }

        if (! schemaTableExists('accounting_manual_entries')) {
            Schema::create('accounting_manual_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // Unité de modification/suppression : les lignes d'une même
                // écriture manuelle partagent ce uuid (elles portent
                // source_type=manual et ce manual_ref comme source_id dérivé).
                $table->uuid('manual_ref');
                $table->date('entry_date');
                $table->char('period', 7);
                $table->string('journal_code', 6)->nullable();
                $table->string('piece', 64);
                $table->string('description', 500)->nullable();
                $table->string('created_by', 255)->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'piece'], 'manual_entries_company_piece_unique');
                $table->index(['company_id', 'period']);
            });
        }

        if (! schemaHasColumn('accounting_journal_entries', 'line_no')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->unsignedSmallInteger('line_no')->default(1)->after('journal_code');
                // Discriminant du groupe d'écriture manuelle : les lignes d'un
                // même OD le partagent, et c'est lui que l'on modifie/supprime.
                $table->uuid('manual_ref')->nullable()->after('line_no');
                $table->index(['company_id', 'manual_ref'], 'journal_entries_manual_ref_idx');
            });
        }

        // Remplace l'ancienne contrainte d'idempotence par la même, élargie au
        // numéro de ligne. On la retire avant de la recréer : PostgreSQL refuse
        // deux contraintes uniques redondantes sur les mêmes colonnes.
        DB::statement('ALTER TABLE accounting_journal_entries DROP CONSTRAINT IF EXISTS journal_source_account_unique');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS journal_source_account_line_unique
            ON accounting_journal_entries (company_id, source_type, source_id, account_code, line_no)
        SQL);
    }

    public function down(): void
    {
        if (! schemaTableExists('accounting_journal_entries')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS journal_source_account_line_unique');
        DB::statement(
            'ALTER TABLE accounting_journal_entries ADD CONSTRAINT journal_source_account_unique '
            .'UNIQUE (company_id, source_type, source_id, account_code)'
        );

        if (schemaTableExists('accounting_manual_entries')) {
            Schema::dropIfExists('accounting_manual_entries');
        }

        if (schemaHasColumn('accounting_journal_entries', 'manual_ref')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->dropIndex('journal_entries_manual_ref_idx');
                $table->dropColumn('manual_ref');
            });
        }

        if (schemaHasColumn('accounting_journal_entries', 'line_no')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->dropColumn('line_no');
            });
        }
    }
};
