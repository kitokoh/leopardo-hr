<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #5272 — Signature HMAC du webhook passerelle invalide ou secret absent
 * (fail-closed, pattern #2615). Aucun traitement : la passerelle doit
 * retenter/alerter.
 */
class WebhookSignatureInvalidException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'WEBHOOK_SIGNATURE_INVALID: webhook signature missing, invalid or gateway not configured',
            401,
            'WEBHOOK_SIGNATURE_INVALID'
        );
    }
}
