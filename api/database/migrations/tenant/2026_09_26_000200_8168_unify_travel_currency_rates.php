<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #8168 — Unification des deux systèmes de conversion de devises Travel.
 *
 * La table `travel_currency_rates` portait DEUX jeux de colonnes :
 * - legacy : `base_currency` / `quote_currency` / `rate` (décimal) /
 *   `valid_until`, lus uniquement par `TravelCurrencyService` (boutique) ;
 * - canonique : `from_currency` / `to_currency` / `rate_minor` (entier
 *   ×10000) / `valid_to`, écrits par l'API (`UpsertCurrencyRateAction`) et
 *   lus par `TravelCurrencyConverter`.
 *
 * Conséquence avant ce correctif : un taux configuré via l'API était
 * INVISIBLE pour la conversion d'affichage de la boutique (TRAVEL-805
 * inapplicable). Le module converge sur le contrat entier (math entière,
 * aucune perte d'arrondi) ; `TravelCurrencyService` est supprimé.
 *
 * Cette migration, **forward-only et idempotente** (Render rejoue des
 * migrations, cf. AGENTS.md) :
 *  1. BACKFILL : recopie les données legacy dans les colonnes canoniques
 *     pour les lignes écrites avant l'unification (`rate_minor` =
 *     `round(rate × 10000)` — le taux legacy a 8 décimales, la troncature
 *     à 4 décimales est le contrat canonique documenté) ;
 *  2. supprime l'index unique legacy
 *     `travel_currency_rates_company_pair_period_unique`
 *     (company_id, base_currency, quote_currency, valid_from) ;
 *  3. supprime les colonnes legacy (aucune perte : déjà recopiées à
 *     l'étape 1 ; le code ne les lit plus depuis la suppression de
 *     `TravelCurrencyService`).
 *
 * `down()` recrée les colonnes legacy (nullable) et y rebascule les données
 * canoniques — structure restaurée, contenu best-effort (le taux entier
 * ×10000 ne restitue pas les 8 décimales d'origine).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_COLUMNS = ['base_currency', 'quote_currency', 'rate', 'valid_until'];

    public function up(): void
    {
        if (! schemaTableExists('travel_currency_rates')) {
            return;
        }

        // 1. Backfill legacy → canonique (uniquement là où le canonique est
        //    absent : rejouable, jamais d'écrasement d'une valeur API).
        if (schemaHasColumn('travel_currency_rates', 'base_currency') && schemaHasColumn('travel_currency_rates', 'from_currency')) {
            DB::statement('UPDATE travel_currency_rates SET from_currency = base_currency WHERE from_currency IS NULL AND base_currency IS NOT NULL');
        }

        if (schemaHasColumn('travel_currency_rates', 'quote_currency') && schemaHasColumn('travel_currency_rates', 'to_currency')) {
            DB::statement('UPDATE travel_currency_rates SET to_currency = quote_currency WHERE to_currency IS NULL AND quote_currency IS NOT NULL');
        }

        if (schemaHasColumn('travel_currency_rates', 'rate') && schemaHasColumn('travel_currency_rates', 'rate_minor')) {
            DB::statement('UPDATE travel_currency_rates SET rate_minor = ROUND(rate * 10000)::bigint WHERE rate_minor IS NULL AND rate IS NOT NULL');
        }

        if (schemaHasColumn('travel_currency_rates', 'valid_until') && schemaHasColumn('travel_currency_rates', 'valid_to')) {
            DB::statement('UPDATE travel_currency_rates SET valid_to = valid_until WHERE valid_to IS NULL AND valid_until IS NOT NULL');
        }

        // 2. Index unique legacy (ses colonnes sont supprimées à l'étape 3).
        //    Créé via `$table->unique()` : sur PostgreSQL c'est une
        //    CONTRAINTE (DROP INDEX échouerait en 2BP01), d'où DROP CONSTRAINT.
        DB::statement('ALTER TABLE travel_currency_rates DROP CONSTRAINT IF EXISTS travel_currency_rates_company_pair_period_unique');

        // 3. Colonnes legacy.
        foreach (self::LEGACY_COLUMNS as $column) {
            if (! schemaHasColumn('travel_currency_rates', $column)) {
                continue;
            }

            Schema::table('travel_currency_rates', static function (Blueprint $table) use ($column): void {
                $table->dropColumn($column);
            });
        }
    }

    public function down(): void
    {
        if (! schemaTableExists('travel_currency_rates')) {
            return;
        }

        if (! schemaHasColumn('travel_currency_rates', 'base_currency')) {
            Schema::table('travel_currency_rates', static function (Blueprint $table): void {
                $table->char('base_currency', 3)->nullable();
                $table->char('quote_currency', 3)->nullable();
                $table->decimal('rate', 18, 8)->nullable();
                $table->date('valid_until')->nullable();
            });
        }

        // Rebascule best-effort canonique → legacy (le taux entier ×10000 ne
        // restitue pas les 8 décimales historiques).
        DB::statement('UPDATE travel_currency_rates SET base_currency = from_currency WHERE base_currency IS NULL AND from_currency IS NOT NULL');
        DB::statement('UPDATE travel_currency_rates SET quote_currency = to_currency WHERE quote_currency IS NULL AND to_currency IS NOT NULL');
        DB::statement('UPDATE travel_currency_rates SET rate = rate_minor / 10000.0 WHERE rate IS NULL AND rate_minor IS NOT NULL');
        DB::statement('UPDATE travel_currency_rates SET valid_until = valid_to WHERE valid_until IS NULL AND valid_to IS NOT NULL');

        if (! Schema::hasIndex('travel_currency_rates', 'travel_currency_rates_company_pair_period_unique')) {
            Schema::table('travel_currency_rates', static function (Blueprint $table): void {
                $table->unique(
                    ['company_id', 'base_currency', 'quote_currency', 'valid_from'],
                    'travel_currency_rates_company_pair_period_unique',
                );
            });
        }
    }
};
