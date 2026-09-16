<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use Carbon\CarbonImmutable;

/**
 * TRAVEL-805 (#6096) — Convertisseur multi-devise tenant-scoped.
 *
 * Conversion en math entière : `montant_minor × rate_minor / 10000` — aucun
 * flottant intermédiaire, aucune perte d'arrondi (acceptance TRAVEL-805).
 * Le taux applicable est celui dont la période [valid_from, valid_to]
 * contient la date demandée (valid_to NULL = période ouverte). Échec 422
 * explicite si aucune période ne couvre la date (fail-closed).
 */
final class TravelCurrencyConverter
{
    /**
     * Convertit un montant en unités mineures d'une devise vers une autre.
     *
     * @return array{amount_minor: int, currency: string, rate_minor: int}
     */
    public function convert(int $amountMinor, string $fromCurrency, string $toCurrency, ?string $date = null): array
    {
        if ($fromCurrency === $toCurrency) {
            return ['amount_minor' => $amountMinor, 'currency' => $toCurrency, 'rate_minor' => TravelCurrencyRate::RATE_SCALE];
        }

        $rate = $this->resolveRate($fromCurrency, $toCurrency, $date);

        // Le schéma de référence (2026_08_30_000020_6096) porte `rate` (décimal)
        // et la migration de réparation #7452 ajoute `rate_minor` en NULLABLE :
        // une ligne dont le taux entier n'est pas renseigné ne peut pas être
        // convertie. Échec 422 explicite (fail-closed), comme pour l'absence de
        // période — jamais de conversion silencieuse à 0.
        $rateMinor = $rate->rate_minor;
        if ($rateMinor === null) {
            abort(422, 'Taux de change non renseigné pour cette période (rate_minor manquant).');
        }

        $converted = intdiv(
            (int) round($amountMinor * $rateMinor / TravelCurrencyRate::RATE_SCALE),
            1
        );

        return [
            'amount_minor' => $converted,
            'currency' => $toCurrency,
            'rate_minor' => $rateMinor,
        ];
    }

    /**
     * Taux applicable à la date (période la plus récente si plusieurs).
     */
    public function resolveRate(string $fromCurrency, string $toCurrency, ?string $date = null): TravelCurrencyRate
    {
        $target = $date !== null
            ? CarbonImmutable::parse($date)->toDateString()
            : CarbonImmutable::today()->toDateString();

        $rate = TravelCurrencyRate::query()
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->whereDate('valid_from', '<=', $target)
            ->where(function ($query) use ($target) {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $target);
            })
            ->orderByDesc('valid_from')
            ->first();

        if (! $rate instanceof TravelCurrencyRate) {
            abort(422, "Aucun taux de conversion {$fromCurrency}→{$toCurrency} pour la date {$target}.");
        }

        return $rate;
    }
}
