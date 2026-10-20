<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * Aucune instance de fournisseur resolvable pour un marche (BC-38, #8686).
 *
 * Erreur metier explicite : jamais de `null` silencieux, jamais de repli
 * implicite sur une instance inactive.
 */
class ProviderNotResolvableException extends RuntimeException
{
    public static function forMarket(string $country, string $currency, ?string $companyId): self
    {
        return new self(sprintf(
            'Aucune instance de fournisseur active pour le marche pays=%s devise=%s tenant=%s.',
            $country,
            $currency,
            $companyId ?? 'global',
        ));
    }
}
