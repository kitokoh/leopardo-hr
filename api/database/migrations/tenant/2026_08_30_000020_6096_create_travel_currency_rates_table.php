<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #6096 (TRAVEL-805) — Multi-devise.
 *
 * - `travel_currency_rates` : taux de conversion VALIDÉS PAR PÉRIODE
 *   (valid_from/valid_until) par (tenant, paire de devises) — critère
 *   d'acceptation ; les montants restent canoniques en minor units de la
 *   devise de référence (aucune perte d'arrondi : conversion à l'affichage
 *   uniquement).
 * - `travel_payments` : colonnes d'affichage multi-devise (montant et
 *   devise de présentation au paiement) — la référence reste
 *   `amount_minor`/`currency`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_currency_rates')) {
            Schema::create('travel_currency_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();
                $table->char('base_currency', 3);
                $table->char('quote_currency', 3);
                $table->decimal('rate', 18, 8);
                $table->date('valid_from');
                $table->date('valid_until')->nullable();
                $table->timestamps();

                $table->unique(
                    ['company_id', 'base_currency', 'quote_currency', 'valid_from'],
                    'travel_currency_rates_company_pair_period_unique',
                );
            });
        }

        if (! schemaHasColumn('travel_payments', 'display_currency')) {
            Schema::table('travel_payments', function (Blueprint $table): void {
                $table->char('display_currency', 3)->nullable();
                $table->unsignedBigInteger('display_amount_minor')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('travel_payments', function (Blueprint $table): void {
            $table->dropColumn(['display_currency', 'display_amount_minor']);
        });

        Schema::dropIfExists('travel_currency_rates');
    }
};
