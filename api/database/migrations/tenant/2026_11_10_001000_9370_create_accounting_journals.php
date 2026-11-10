<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9370.
 *
 * Jusqu'ici le **journal** d'une écriture n'existait pas en base : `FecExporter`
 * le devinait à l'export à partir du préfixe du numéro de compte
 * (`str_starts_with($account_code, '6')` → `AC`, `7`/`411` → `VE`, sinon `OD`).
 * Trois conséquences fâcheuses, toutes vérifiables :
 *
 *   - une même facture se retrouvait **éclatée** entre `VE` (client, produit)
 *     et `OD` (la ligne de TVA `4457` ne commence ni par 6, ni par 7, ni par
 *     `411`) — l'import chez un expert-comptable casse le lettrage ;
 *   - un tenant turc postant `120` (Tekdüzen) tombait en `OD` ;
 *   - la classification pouvait **changer entre deux exports** sans qu'aucune
 *     écriture n'ait bougé.
 *
 * Cette migration crée l'entité `accounting_journals` (code, libellé, type,
 * compte de trésorerie par défaut) et ajoute `journal_code` aux écritures.
 * Le backfill reprend **exactement** l'ancienne heuristique : l'historique
 * exporté reste identique, seules les nouvelles écritures sont correctes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_journals')) {
            Schema::create('accounting_journals', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // Code FEC du journal (2 à 6 caractères : VE, AC, BQ, OD, SATIS…).
                $table->string('code', 6);
                $table->string('label', 120);
                // sale | purchase | bank | cash | payroll | misc | opening
                $table->string('type', 20);
                // Compte de trésorerie par défaut du journal (nullable hors trésorerie).
                $table->string('default_account_code', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'accounting_journals_company_code_unique');
                $table->index(['company_id', 'type']);
            });
        }

        if (schemaTableExists('accounting_journal_entries') && ! schemaHasColumn('accounting_journal_entries', 'journal_code')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->string('journal_code', 6)->nullable()->after('period');
                $table->index(['company_id', 'journal_code'], 'journal_entries_company_journal_idx');
            });

            // Backfill : reproduit l'heuristique historique de FecExporter pour
            // que les exports déjà produits restent identiques. Les nouvelles
            // écritures reçoivent leur journal depuis le type de document.
            DB::statement(<<<'SQL'
                UPDATE accounting_journal_entries
                SET journal_code = CASE
                    WHEN source_type = 'payment' THEN 'TR'
                    WHEN source_type = 'document' AND (account_code LIKE '6%') THEN 'AC'
                    WHEN source_type = 'document' AND (account_code LIKE '7%' OR account_code = '411') THEN 'VE'
                    ELSE 'OD'
                END
                WHERE journal_code IS NULL
            SQL);
        }
    }

    public function down(): void
    {
        if (schemaTableExists('accounting_journal_entries') && schemaHasColumn('accounting_journal_entries', 'journal_code')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->dropIndex('journal_entries_company_journal_idx');
                $table->dropColumn('journal_code');
            });
        }

        Schema::dropIfExists('accounting_journals');
    }
};
