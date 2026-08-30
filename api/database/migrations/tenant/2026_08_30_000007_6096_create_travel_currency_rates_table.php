<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6096 (TRAVEL-805) — travel_currency_rates : taux de conversion par tenant.
 *
 * Taux stocké en `rate_minor` = taux × 10000 (entier, 4 décimales) — les
 * conversions restent en math entière (aucune perte d'arrondi, spec §12).
 * Un taux est valide sur une période [valid_from, valid_to] ; les périodes
 * d'une même paire (from, to) ne doivent pas se chevaucher (validé
 * applicativement, pas de contrainte DB partielle).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('travel_currency_rates')) {
            Schema::create('travel_currency_rates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->char('from_currency', 3);
                $table->char('to_currency', 3);
                $table->unsignedBigInteger('rate_minor');
                $table->date('valid_from');
                $table->date('valid_to')->nullable();

                $table->timestamps();

                $table->unique(
                    ['company_id', 'from_currency', 'to_currency', 'valid_from'],
                    'travel_currency_rates_company_pair_period_unique'
                );
                $table->index(['company_id', 'from_currency', 'to_currency'], 'travel_currency_rates_company_pair_idx');
            });

            DB::statement("COMMENT ON TABLE travel_currency_rates IS 'Taux de conversion par tenant — rate_minor = taux × 10000 (TRAVEL-805/#6096).'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_currency_rates');
    }
};
