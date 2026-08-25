<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5272 — Aucune passerelle de paiement en ligne n'est configurée pour le
 * pays de l'entreprise (hors périmètre ADR-0017 option A), ou la passerelle
 * désignée n'a pas de clé API configurée. Fail-closed : on refuse le checkout
 * au lieu de proposer un paiement non routable.
 */
class PaymentGatewayNotConfiguredException extends DomainException
{
    public function __construct(string $country)
    {
        parent::__construct(
            sprintf('PAYMENT_GATEWAY_NOT_CONFIGURED: no online gateway for country "%s"', $country),
            422,
            'PAYMENT_GATEWAY_NOT_CONFIGURED'
        );
    }
}
