<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9375.
 *
 * Le module n'avait **aucune comptabilité analytique** : `accounting_documents`
 * porte bien un `project_ref` (chaîne libre), mais il n'était relié à aucun
 * référentiel, jamais reporté sur l'écriture, et rien ne permettait de comparer
 * un réalisé à un budget. Un tenant ne pouvait donc pas répondre à « combien
 * a coûté le chantier X ce trimestre ? » ni à « suis-je dans mon budget ? ».
 *
 * Cette migration ajoute :
 *   - `accounting_cost_centers` : référentiel des axes analytiques du tenant ;
 *   - `accounting_journal_entries.cost_center_id` : l'axe porté par l'écriture
 *     (et non par le document — une facture peut ventiler plusieurs axes) ;
 *   - `accounting_budgets` : enveloppe par axe, par exercice et par préfixe de
 *     compte, ce qui permet un budget global ou détaillé par nature de charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_cost_centers')) {
            Schema::create('accounting_cost_centers', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('label', 120);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'accounting_cost_centers_company_code_unique');
            });
        }

        if (schemaTableExists('accounting_journal_entries') && ! schemaHasColumn('accounting_journal_entries', 'cost_center_id')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                // Référence logique (pas de FK physique : le référentiel est par
                // tenant et supprimable). Nullable : une écriture sans axe reste
                // valide — l'analytique est optionnelle, jamais bloquante.
                $table->unsignedBigInteger('cost_center_id')->nullable()->after('journal_code');
                $table->index(['company_id', 'cost_center_id'], 'journal_entries_company_cost_center_idx');
            });
        }

        if (! schemaTableExists('accounting_budgets')) {
            Schema::create('accounting_budgets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // Axe analytique ; null = budget global (tous axes confondus).
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedSmallInteger('year');
                // Préfixe de compte : '6' = toutes les charges, '60' = achats,
                // '61' = services extérieurs… null = toutes natures.
                $table->string('account_prefix', 10)->nullable();
                $table->decimal('amount', 15, 2);
                $table->string('label', 120)->nullable();
                $table->timestamps();

                $table->index(['company_id', 'year']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_budgets');

        if (schemaTableExists('accounting_journal_entries') && schemaHasColumn('accounting_journal_entries', 'cost_center_id')) {
            Schema::table('accounting_journal_entries', function (Blueprint $table): void {
                $table->dropIndex('journal_entries_company_cost_center_idx');
                $table->dropColumn('cost_center_id');
            });
        }

        Schema::dropIfExists('accounting_cost_centers');
    }
};
