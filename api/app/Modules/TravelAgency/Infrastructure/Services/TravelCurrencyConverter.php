<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use Carbon\CarbonImmutable;

/**
 * TRAVEL-805 (#6096) — Convertisseur multi-devise tenant-scoped.
 *
 * Service de conversion UNIQUE du module (#8168) : la boutique
 * (`TravelShopController`) et l'endpoint `currency-rates/convert` lisent le
 * même contrat canonique `from_currency` / `to_currency` / `rate_minor` /
 * [valid_from, valid_to]. Le service legacy `TravelCurrencyService`
 * (colonnes `base_currency` / `quote_currency` / `rate` flottant) a été
 * supprimé : un taux configuré via l'API est désormais visible partout.
 *
 * Conversion en math entière — aucun flottant intermédiaire :
 * - paire directe : `montant_minor × rate_minor / 10000` ;
 * - paire inverse (#8168, critère TRAVEL-805 « paire inverse supportée ») :
 *   `montant_minor × 10000 / rate_minor` — la conversion inverse utilise le
 *   taux entier stocké, sans représentation flottante de `1 / rate`.
 *
 * Le taux applicable est celui dont la période [valid_from, valid_to]
 * contient la date demandée (valid_to NULL = période ouverte) ; la paire
 * directe prime sur la paire inverse. Échec 422 explicite si aucune période
 * ne couvre la date dans aucun des deux sens (fail-closed).
 */
final class TravelCurrencyConverter
{
    /**
     * Convertit un montant en unités mineures d'une devise vers une autre.
     *
     * @return array{amount_minor: int, currency: string, rate_minor: int, inverse: bool}
     *                                                                                    `rate_minor` est le taux entier STOCKÉ de la ligne utilisée ;
     *                                                                                    quand `inverse` est true, la conversion appliquée est
     *                                                                                    `amount_minor × RATE_SCALE / rate_minor` (pas de taux
     *                                                                                    inverse arrondi, qui serait mensonger).
     */
    public function convert(int $amountMinor, string $fromCurrency, string $toCurrency, ?string $date = null): array
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return [
                'amount_minor' => $amountMinor,
                'currency' => $toCurrency,
                'rate_minor' => TravelCurrencyRate::RATE_SCALE,
                'inverse' => false,
            ];
        }

        [$rate, $inverse] = $this->resolveRateWithDirection($fromCurrency, $toCurrency, $date);

        // `rate_minor` est nullable dans le schéma historique (migration de
        // réparation #7452) ; la migration d'unification #8168 a backfillé
        // les lignes legacy. Une ligne sans taux entier reste inconvertible :
        // échec 422 explicite (fail-closed) — jamais de conversion à 0.
        $rateMinor = $rate->rate_minor;
        if ($rateMinor === null || $rateMinor < 1) {
            abort(422, 'Taux de change non renseigné pour cette période (rate_minor manquant).');
        }

        $converted = $inverse
            ? (int) round($amountMinor * TravelCurrencyRate::RATE_SCALE / $rateMinor)
            : (int) round($amountMinor * $rateMinor / TravelCurrencyRate::RATE_SCALE);

        return [
            'amount_minor' => $converted,
            'currency' => $toCurrency,
            'rate_minor' => $rateMinor,
            'inverse' => $inverse,
        ];
    }

    /**
     * Taux applicable à la date pour la paire DIRECTE uniquement (période
     * la plus récente si plusieurs). Conservé pour les appelants qui
     * exigent le sens exact (échec 422 si absent, pas de repli inverse).
     */
    public function resolveRate(string $fromCurrency, string $toCurrency, ?string $date = null): TravelCurrencyRate
    {
        $target = $this->targetDate($date);

        $rate = $this->rateQuery(strtoupper($fromCurrency), strtoupper($toCurrency), $target)->first();

        if (! $rate instanceof TravelCurrencyRate) {
            abort(422, "Aucun taux de conversion {$fromCurrency}→{$toCurrency} pour la date {$target}.");
        }

        return $rate;
    }

    /**
     * Taux applicable à la date, paire directe d'abord puis paire inverse.
     *
     * @return array{0: TravelCurrencyRate, 1: bool} (taux utilisé, inverse ?)
     */
    private function resolveRateWithDirection(string $fromCurrency, string $toCurrency, ?string $date): array
    {
        $target = $this->targetDate($date);

        $direct = $this->rateQuery($fromCurrency, $toCurrency, $target)->first();
        if ($direct instanceof TravelCurrencyRate) {
            return [$direct, false];
        }

        $inverse = $this->rateQuery($toCurrency, $fromCurrency, $target)->first();
        if ($inverse instanceof TravelCurrencyRate) {
            return [$inverse, true];
        }

        abort(422, "Aucun taux de conversion {$fromCurrency}→{$toCurrency} pour la date {$target}.");
    }

    private function targetDate(?string $date): string
    {
        return $date !== null
            ? CarbonImmutable::parse($date)->toDateString()
            : CarbonImmutable::today()->toDateString();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TravelCurrencyRate>
     */
    private function rateQuery(string $fromCurrency, string $toCurrency, string $target): \Illuminate\Database\Eloquent\Builder
    {
        return TravelCurrencyRate::query()
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereDate('valid_from', '<=', $target)
            ->where(function ($query) use ($target) {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $target);
            })
            ->orderByDesc('valid_from');
    }
}
