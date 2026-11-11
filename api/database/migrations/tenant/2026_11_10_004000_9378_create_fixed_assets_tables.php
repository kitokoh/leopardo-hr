<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module Comptabilité — Issue #9378.
 *
 * Le module n'avait **aucune** notion d'immobilisation ni d'amortissement :
 * `ChartAccountResolver` ne connaissait ni les comptes 21/281/681 (PCG) ni leurs
 * équivalents par pays, et rien ne permettait de constater une dotation. Un
 * tenant ne pouvait donc pas produire un bilan conforme : les immobilisations
 * achetées restaient en charges de l'exercice, et le résultat était surévalué
 * du montant des amortissements non pratiqués.
 *
 * Cette migration crée le registre des immobilisations et le plan de dotation
 * associé. Le calcul est **linéaire** (le seul mode commun à tous les
 * référentiels supportés) ; les modes dégressifs/unités d'œuvre restent hors
 * périmètre et sont signalés comme tels dans l'issue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('accounting_fixed_assets')) {
            Schema::create('accounting_fixed_assets', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->string('code', 40);
                $table->string('label', 180);
                // Date de mise en service : point de départ de la dotation.
                $table->date('in_service_date');
                $table->decimal('acquisition_cost', 15, 2);
                // Valeur résiduelle non amortissable (souvent 0).
                $table->decimal('residual_value', 15, 2)->default(0);
                // Durée d'utilité en mois (36, 60, 120…).
                $table->unsignedSmallInteger('useful_life_months');
                // linear uniquement pour l'instant — la colonne existe pour que
                // l'ajout d'un mode ne casse pas le schéma.
                $table->string('method', 20)->default('linear');
                $table->char('currency', 3)->nullable();
                $table->string('status', 20)->default('active');
                $table->date('disposed_on')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'code'], 'accounting_fixed_assets_company_code_unique');
                $table->index(['company_id', 'status']);
            });
        }

        if (! schemaTableExists('accounting_depreciations')) {
            Schema::create('accounting_depreciations', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->unsignedBigInteger('fixed_asset_id')->index();
                // Période de la dotation (Y-m) : une seule dotation par actif et
                // par période — c'est l'idempotence du calcul.
                $table->string('period', 7);
                $table->date('entry_date');
                $table->decimal('amount', 15, 2);
                $table->decimal('accumulated_after', 15, 2);
                $table->decimal('net_book_value_after', 15, 2);
                $table->char('currency', 3)->nullable();
                // Écriture générée (source_type = 'depreciation').
                $table->unsignedBigInteger('journal_entry_source_id')->nullable();
                $table->timestamps();

                $table->unique(['fixed_asset_id', 'period'], 'accounting_depreciations_asset_period_unique');
                $table->index(['company_id', 'period']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_depreciations');
        Schema::dropIfExists('accounting_fixed_assets');
    }
};
