<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5234.
 *
 * Tables tenant (`shared_tenants`) du journal des écritures :
 *   - accounting_journal_entries  : écritures débit/crédit dérivées des
 *     documents (invoice/credit_note) et des paiements — une ligne = un
 *     compte ; paire (débit, crédit) exclusive (check) ; équilibre global
 *     garanti par le service de posting (Σ débit = Σ crédit).
 *   - accounting_closed_periods   : périodes clôturées (fige le journal —
 *     plus aucun posting accepté pour cette période).
 *
 * Règles (mêmes conventions que #5221) :
 *   - migration additive et idempotente (garde schemaTableExists) ;
 *   - company_id uuid NON nullable — isolation tenant via BelongsToCompany ;
 *   - numérotation de fichier 000003 pour éviter toute collision avec les
 *     migrations 000001/000002 portées par les branches #5223/#5225 (non
 *     mergées sur main au moment de l'écriture).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_journal_entries')) {
            Schema::create('accounting_journal_entries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->date('entry_date');
                // Période comptable YYYY-MM dérivée de entry_date — indexée pour le journal.
                $table->char('period', 7);
                // document | payment
                $table->string('source_type', 20);
                $table->unsignedBigInteger('source_id');
                // Plan comptable PCF/SYSCOHADA simplifié (cf. JournalPostingService).
                $table->string('account_code', 20);
                $table->string('account_label', 255);
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                // Pièce comptable (n° document ou PAY-{id}) — colonne d'export
                // utilisée par l'expert-comptable (libre de référence).
                $table->string('piece', 64)->nullable();
                $table->string('description', 500)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'period']);
                $table->index(['company_id', 'entry_date']);
                // Idempotence du re-posting : une ligne par (source, compte).
                $table->unique(['company_id', 'source_type', 'source_id', 'account_code'], 'journal_source_account_unique');
            });

            // Une écriture est toujours à sens unique : débit OU crédit, jamais les deux.
            // (Blueprint::check n'existe pas dans cette version de Laravel → SQL brut.)
            DB::statement(
                'ALTER TABLE accounting_journal_entries ADD CONSTRAINT journal_debit_credit_exclusive CHECK ((debit = 0) <> (credit = 0))'
            );
        }

        if (! schemaTableExists('accounting_closed_periods')) {
            Schema::create('accounting_closed_periods', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->char('period', 7);
                $table->string('closed_by', 255)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'period'], 'closed_period_company_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_closed_periods');
        Schema::dropIfExists('accounting_journal_entries');
    }
};
