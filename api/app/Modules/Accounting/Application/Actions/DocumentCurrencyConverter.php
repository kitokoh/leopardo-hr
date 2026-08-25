<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Application\DTOs\ConvertedAmount;
use App\Modules\Accounting\Application\DTOs\ConvertedTotals;
use App\Modules\Accounting\Domain\Contracts\CurrencyRateProviderInterface;
use App\Modules\Accounting\Domain\Exceptions\CurrencyRateUnavailableException;
use App\Modules\Accounting\Domain\Models\AccountingDocument;
use App\Modules\Accounting\Domain\Support\AccountingCurrencies;

/**
 * Conversion multi-devises des montants comptables — issue #5270.
 *
 * Règles d'arrondi (documentées dans
 * `.specify/features/5270-multi-currency/spec.md`) :
 *   - tout montant monétaire exposé est arrondi HALF-UP à 2 décimales ;
 *   - la TVA est calculée dans la devise du document (jamais convertie
 *     avant son calcul) ; les totaux (HT/TVA/TTC) sont ensuite convertis ;
 *   - `exchange_rate` = valeur de 1 unité de la devise du document dans la
 *     devise de référence (multiplication) ;
 *   - devises identiques → taux 1, source 'identity' (aucun provider requis) ;
 *   - ordre de résolution du taux : taux manuel explicite → provider externe
 *     (CurrencyRateProviderInterface) → sinon CurrencyRateUnavailableException
 *     (jamais de taux inventé).
 */
final class DocumentCurrencyConverter
{
    public const DECIMALS = 2;

    public const ROUNDING_MODE = 'half_up';

    public const SOURCE_IDENTITY = 'identity';

    public const SOURCE_MANUAL = 'manual';

    public function __construct(private readonly ?CurrencyRateProviderInterface $provider = null) {}

    /**
     * Convertit un montant de $from vers $to.
     *
     * @throws CurrencyRateUnavailableException si aucun taux n'est résoluble
     * @throws \InvalidArgumentException si un code devise est invalide
     */
    public function convertAmount(float $amount, string $from, string $to, ?float $rate = null): ConvertedAmount
    {
        $from = $this->requireCurrency($from);
        $to = $this->requireCurrency($to);

        [$rate, $source] = $this->resolveRate($from, $to, $rate);

        return new ConvertedAmount(
            amount: $amount,
            fromCurrency: $from,
            toCurrency: $to,
            rate: $rate,
            convertedAmount: $this->roundAmount($amount * $rate),
            source: $source,
        );
    }

    /**
     * Totaux d'un document dans sa devise + dans la devise de référence.
     * Si le document n'a pas de devise (legacy), il est traité comme étant
     * déjà dans la devise de référence (conversion identité).
     *
     * @throws CurrencyRateUnavailableException si aucun taux n'est résoluble
     * @throws \InvalidArgumentException si un code devise est invalide
     */
    public function convertTotals(AccountingDocument $document, string $referenceCurrency, ?float $rate = null): ConvertedTotals
    {
        $referenceCurrency = $this->requireCurrency($referenceCurrency);
        $documentCurrency = $this->requireCurrency($document->currency ?? $referenceCurrency);

        [$rate, $source] = $this->resolveRate($documentCurrency, $referenceCurrency, $rate);

        return new ConvertedTotals(
            documentCurrency: $documentCurrency,
            referenceCurrency: $referenceCurrency,
            rate: $rate,
            source: $source,
            subtotalHt: (float) $document->subtotal_ht,
            taxAmount: (float) $document->tax_amount,
            totalTtc: (float) $document->total_ttc,
            subtotalHtConverted: $this->roundAmount((float) $document->subtotal_ht * $rate),
            taxAmountConverted: $this->roundAmount((float) $document->tax_amount * $rate),
            totalTtcConverted: $this->roundAmount((float) $document->total_ttc * $rate),
        );
    }

    /**
     * Résout le taux et sa source selon l'ordre documenté.
     *
     * @return array{0: float, 1: string}
     */
    private function resolveRate(string $from, string $to, ?float $rate): array
    {
        if ($from === $to) {
            return [1.0, self::SOURCE_IDENTITY];
        }

        if ($rate !== null && $rate > 0.0) {
            return [$rate, self::SOURCE_MANUAL];
        }

        if ($this->provider !== null && $this->provider->supports($from, $to)) {
            $providerRate = $this->provider->rate($from, $to);

            if ($providerRate > 0.0) {
                return [$providerRate, $this->provider->source()];
            }
        }

        throw new CurrencyRateUnavailableException($from, $to);
    }

    private function roundAmount(float $amount): float
    {
        return round($amount, self::DECIMALS, PHP_ROUND_HALF_UP);
    }

    /**
     * Normalise un code devise ISO 4217 (3 lettres, majuscules).
     *
     * @throws \InvalidArgumentException
     */
    private function requireCurrency(?string $currency): string
    {
        $normalized = AccountingCurrencies::normalize($currency);

        if ($normalized === null) {
            throw new \InvalidArgumentException(sprintf('INVALID_CURRENCY_CODE: %s', (string) $currency));
        }

        return $normalized;
    }
}
