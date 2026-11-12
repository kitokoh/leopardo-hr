<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9373.
 *
 * La multi-devises existait **au niveau du document** mais **pas au niveau de
 * l'écriture** : `accounting_documents` porte `currency` + `exchange_rate`
 * (converti en HT/TVA/TTC par `DocumentCurrencyConverter`), tandis que
 * `accounting_journal_entries` ne portait ni devise ni taux. Conséquences
 * vérifiables :
 *
 *   - un tenant DZD qui facture 1 000 EUR comptabilisait **1 000 DZD** sur son
 *     compte `411` (`JournalPostingService::salesInvoiceLines()` postait
 *     `total_ttc` en devise du document) — soit une perte de change de 100 % ;
 *   - le grand livre, la balance, le bilan et le résultat additionnaient des
 *     montants de devises différentes sans conversion ;
 *   - l'export FEC écrivait la devise de la `Company` sur des lignes exprimées
 *     dans une autre devise ;
 *   - la TVA déclarée agrégeait des `tax_amount` hétérogènes.
 *
 * Cette migration ajoute les colonnes additives qui rendent la conversion
 * traçable et auditable :
 *   - `currency`        : devise de **l'écriture** = devise de référence du
 *                         tenant (celle dans laquelle le montant est comptabilisé) ;
 *   - `amount_currency` : devise d'**origine** du document (celle du montant
 *                         avant conversion) — conservée pour l'audit ;
 *   - `exchange_rate`   : taux appliqué (1 unité de `amount_currency` vaut
 *                         `exchange_rate` unités de `currency`).
 *
 * Les écritures existantes sont rétro-remplies à taux 1 et dans la devise de
 * leur entreprise : c'est exactement ce qu'elles valaient avant (aucune
 * réécriture d'historique, aucun montant modifié).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_journal_entries')) {
            return;
        }

        if (! schemaHasColumn('accounting_journal_entries', 'currency')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                // Devise de comptabilisation (devise de référence du tenant).
                $table->char('currency', 3)->nullable()->after('period');
                // Devise d'origine du document, avant conversion (audit).
                $table->char('amount_currency', 3)->nullable()->after('currency');
                // 1 unité de amount_currency = exchange_rate unités de currency.
                $table->decimal('exchange_rate', 18, 8)->default(1)->after('amount_currency');
            });

            // Backfill : les écritures antérieures valaient leur montant tel
            // quel, dans la devise de leur entreprise, à taux 1. On ne
            // reconvertit **jamais** un historique (les montants débit/crédit
            // restent intacts, seule la traçabilité de devise est ajoutée).
            //
            // `companies` vit dans le schéma `public` alors que cette migration
            // tourne avec `search_path = shared_tenants` : on qualifie la table
            // explicitement, sinon `relation "companies" does not exist` (42P01)
            // et la migration échoue — donc les colonnes n'existent jamais.
            $companiesTable = $this->publicTable('companies');

            DB::statement(<<<SQL
                UPDATE accounting_journal_entries AS e
                SET currency = COALESCE(
                        (SELECT UPPER(s.currency) FROM accounting_settings AS s
                          WHERE s.company_id = e.company_id LIMIT 1),
                        (SELECT UPPER(c.currency) FROM {$companiesTable} AS c
                          WHERE c.id = e.company_id LIMIT 1),
                        'DZD'
                    ),
                    amount_currency = COALESCE(
                        (SELECT UPPER(s.currency) FROM accounting_settings AS s
                          WHERE s.company_id = e.company_id LIMIT 1),
                        (SELECT UPPER(c.currency) FROM {$companiesTable} AS c
                          WHERE c.id = e.company_id LIMIT 1),
                        'DZD'
                    ),
                    exchange_rate = 1
                WHERE e.currency IS NULL
            SQL);
        }

        // Un solde par devise doit pouvoir être agrégé sans balayer la table :
        // l'indicateur « lignes non converties » des états financiers s'appuie
        // sur (company_id, currency).
        if (! schemaHasIndex('accounting_journal_entries', 'journal_entries_company_currency_idx')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->index(['company_id', 'currency'], 'journal_entries_company_currency_idx');
            });
        }
    }

    /**
     * Nom qualifié d'une table du schéma `public` (les migrations tenant
     * tournent avec `search_path = shared_tenants`, où `companies` n'existe pas).
     */
    private function publicTable(string $table): string
    {
        $schema = DB::selectOne(
            "SELECT table_schema FROM information_schema.tables
              WHERE table_name = ? AND table_schema NOT IN ('shared_tenants', 'pg_catalog', 'information_schema')
              ORDER BY CASE WHEN table_schema = 'public' THEN 0 ELSE 1 END
              LIMIT 1",
            [$table]
        );

        return $schema !== null ? $schema->table_schema.'.'.$table : 'public.'.$table;
    }

    public function down(): void
    {
        if (! schemaTableExists('accounting_journal_entries') || ! schemaHasColumn('accounting_journal_entries', 'currency')) {
            return;
        }

        Schema::table('accounting_journal_entries', function (Blueprint $table): void {
            $table->dropIndex('journal_entries_company_currency_idx');
            $table->dropColumn(['currency', 'amount_currency', 'exchange_rate']);
        });
    }
};
