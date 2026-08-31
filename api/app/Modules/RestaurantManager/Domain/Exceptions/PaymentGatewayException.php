<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Exceptions;

use RuntimeException;

/**
 * RESTO-406 (#6193) — Erreur normalisée d'une passerelle de paiement.
 *
 * `code` est stable et sûr (ex. `provider_unreachable`, `invalid_signature`,
 * `amount_mismatch`) ; `message` ne contient jamais de secret ni de stack
 * trace. L'API convertit cette exception en 422/502 sans fuiter de détail.
 */
final class PaymentGatewayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'payment_gateway_error',
        int $statusCode = 422,
    ) {
        parent::__construct($message, $statusCode);
    }
}
