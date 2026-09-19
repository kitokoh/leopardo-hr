<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * HC-007 (#7791) — le paiement depasse le solde restant de la facture
 * (sur-paiement refuse) → 422.
 */
class HealthInvoiceOverpaymentException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Le paiement depasse le solde restant de la facture.',
            422,
            'HEALTH_INVOICE_OVERPAYMENT'
        );
    }
}
