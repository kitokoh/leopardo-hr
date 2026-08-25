<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * Aucun taux de change disponible pour convertir la paire demandée — issue
 * #5270. Le convertisseur exige soit un taux explicite, soit un provider
 * externe capable de la paire (jamais de taux inventé silencieusement).
 */
final class CurrencyRateUnavailableException extends RuntimeException
{
    public function __construct(string $from, string $to)
    {
        parent::__construct(sprintf(
            'CURRENCY_RATE_UNAVAILABLE: aucun taux de %s vers %s (taux manuel absent, provider indisponible).',
            $from,
            $to,
        ));
    }
}
