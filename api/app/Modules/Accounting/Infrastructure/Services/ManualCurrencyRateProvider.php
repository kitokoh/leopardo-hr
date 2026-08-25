<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Modules\Accounting\Domain\Contracts\CurrencyRateProviderInterface;

/**
 * Taux de change manuel — issue #5270.
 *
 * Implémentation par défaut du contrat : le taux est fourni par l'appelant
 * (valeur de `accounting_documents.exchange_rate` saisie par le comptable,
 * ou champ `rate` de l'endpoint de conversion). Source 'manual'.
 */
final class ManualCurrencyRateProvider implements CurrencyRateProviderInterface
{
    public function __construct(private readonly float $rate) {}

    public function rate(string $from, string $to): float
    {
        return $this->rate;
    }

    public function source(): string
    {
        return 'manual';
    }

    public function supports(string $from, string $to): bool
    {
        return $this->rate > 0.0;
    }
}
