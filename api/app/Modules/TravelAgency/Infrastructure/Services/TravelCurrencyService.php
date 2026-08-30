<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Infrastructure\Services;

use App\Modules\TravelAgency\Domain\Models\TravelCurrencyRate;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * TRAVEL-805 (#6096) — Conversion multi-devise.
 *
 * - Les montants canoniques restent en minor units de la devise de
 *   référence (aucune perte d'arrondi : la conversion n'est appliquée qu'à
 *   l'affichage/paiement, arrondi demi-au-plus haut sur le résultat final) ;
 * - taux VALIDÉS PAR PÉRIODE : la date cible doit tomber dans
 *   [valid_from, valid_until] du taux ;
 * - paire inverse supportée (base↔quote) via l'inverse du taux.
 */
final class TravelCurrencyService
{
    /**
     * Convertit un montant (minor units) à une date donnée.
     */
    public function convert(
        string $companyId,
        int $amountMinor,
        string $from,
        string $to,
        ?DateTimeInterface $at = null,
    ): int {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return $amountMinor;
        }

        $rate = $this->rateFor($companyId, $from, $to, $at);

        if ($rate === null) {
            abort(422, 'Aucun taux de conversion '.$from.' → '.$to.' valide à cette date.');
        }

        return (int) round($amountMinor * $rate);
    }

    /**
     * Taux applicable (paire directe ou inverse) à une date.
     */
    public function rateFor(
        string $companyId,
        string $from,
        string $to,
        ?DateTimeInterface $at = null,
    ): ?float {
        $date = CarbonImmutable::parse($at ?? now())->toDateString();

        /** @var TravelCurrencyRate|null $direct */
        $direct = TravelCurrencyRate::query()
            ->where('company_id', $companyId)
            ->where('base_currency', strtoupper($from))
            ->where('quote_currency', strtoupper($to))
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($direct instanceof TravelCurrencyRate) {
            return (float) $direct->rate;
        }

        /** @var TravelCurrencyRate|null $inverse */
        $inverse = TravelCurrencyRate::query()
            ->where('company_id', $companyId)
            ->where('base_currency', strtoupper($to))
            ->where('quote_currency', strtoupper($from))
            ->whereDate('valid_from', '<=', $date)
            ->where(function ($q) use ($date): void {
                $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($inverse instanceof TravelCurrencyRate && (float) $inverse->rate > 0) {
            return 1 / (float) $inverse->rate;
        }

        return null;
    }
}
