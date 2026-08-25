<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #5422 (consolidation production, profondeur).
 *
 * - accounting_chart_accounts : plan comptable par entreprise (tenant).
 *   Chaque ligne = un compte (code, intitulé, nature bilan/charge/produit,
 *   classe PCG/SCF 1→8, actif/passif). Le plan est provisionné par défaut
 *   selon le pays (PCG français / SCF algérien / plan OHADA simplifié) et
 *   reste entièrement modifiable par l'entreprise (création de comptes
 *   analytiques, désactivation). Le journal poste sur ces comptes.
 * - accounting_fiscal_years : exercices comptables. Un exercice est ouvert
 *   (les écritures de son année sont possibles) ou clôturé (figé — plus
 *   aucun posting, résultat reporté en 12 « report à nouveau »).
 *
 * Migration additive et idempotente (garde schemaTableExists) ; company_id
 * uuid NON nullable — isolation tenant via BelongsToCompany.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_chart_accounts')) {
            Schema::create('accounting_chart_accounts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                // Code de compte (ex. 41100000) — normalisé, unique par entreprise.
                $table->string('code', 20);
                $table->string('label', 255);
                // Nature comptable : asset|liability|equity|revenue|expense.
                $table->string('type', 20);
                // Classe PCG/SCF : 1 capitaux, 2 immobilisations, 3 stocks,
                // 4 tiers, 5 financier, 6 charges, 7 produits, 8 comptes spéciaux.
                $table->unsignedTinyInteger('class')->default(0);
                // Comptes système (provisionnés) — non supprimables, modifiables.
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'chart_company_code_unique');
                $table->index(['company_id', 'type'], 'chart_company_type_idx');
                $table->index(['company_id', 'class'], 'chart_company_class_idx');
            });
        }

        if (! schemaTableExists('accounting_fiscal_years')) {
            Schema::create('accounting_fiscal_years', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedSmallInteger('year');
                // open | closed
                $table->string('status', 10)->default('open');
                $table->string('closed_by', 255)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'year'], 'fiscal_year_company_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fiscal_years');
        Schema::dropIfExists('accounting_chart_accounts');
    }
};
