<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-007 (#7791) — refus métier du flux de facturation des soins.
 *
 * Codes stables consommés par le client web (`front/web/src/lib/health-api.ts`)
 * et les tests de recette. Le code est porté par `errorCode()` (rendu JSON
 * `error`) — passer par `abort()` ne suffit pas : le renderer générique
 * remplacerait le message par un code mappé par statut (pattern EduFeeException).
 */
final class HealthBillingException extends DomainException
{
    private function __construct(string $message, int $statusCode, string $errorCode)
    {
        parent::__construct($message, $statusCode, $errorCode);
    }

    /**
     * L'encaissement dépasserait le total dû (le trop-perçu n'est jamais
     * absorbé silencieusement — cumul paiements ≤ total, spec §4).
     */
    public static function overpayment(): self
    {
        return new self('HEALTH_OVERPAYMENT', 422, 'HEALTH_OVERPAYMENT');
    }

    /**
     * Transition de statut de facture invalide (issue hors brouillon,
     * paiement hors issued/partially_paid, annulation d'une facture payée…).
     */
    public static function invalidStatus(): self
    {
        return new self('HEALTH_INVOICE_STATUS', 422, 'HEALTH_INVOICE_STATUS');
    }

    /**
     * Remise supérieure au sous-total : le total (Σ line_total − discount)
     * doit rester ≥ 0 (spec §4).
     */
    public static function discountExceedsSubtotal(): self
    {
        return new self('HEALTH_INVOICE_DISCOUNT', 422, 'HEALTH_INVOICE_DISCOUNT');
    }
}
