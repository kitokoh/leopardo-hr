<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Issue #9377 — Table des immobilisations pour le module Amortissements.
 *
 * Stocke les immobilisations corporelles et incorporelles avec leur plan
 * d'amortissement (linéaire ou dégressif).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (schemaTableExists('accounting_fixed_assets')) {
            return;
        }

        Schema::create('accounting_fixed_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('company_id');
            $table->string('label', 200);
            $table->string('asset_account_code', 20); // Compte d'immobilisation (ex: 21)
            $table->string('depreciation_account_code', 20); // Compte d'amortissement (ex: 28)
            $table->string('expense_account_code', 20); // Compte de dotation (ex: 681)
            $table->date('acquisition_date');
            $table->decimal('acquisition_cost', 15, 2);
            $table->decimal('residual_value', 15, 2)->default(0);
            $table->integer('useful_life_months'); // Durée d'utilité en mois
            $table->string('method', 20)->default('linear'); // linear | declining
            $table->string('status', 30)->default('active'); // active | disposed | fully_depreciated
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_proceeds', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_fixed_assets');
    }
};
