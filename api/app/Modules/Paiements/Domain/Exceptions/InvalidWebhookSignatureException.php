<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Exceptions;

use RuntimeException;

/**
 * Signature de webhook invalide ou absente (BC-38, #8688).
 *
 * Fail-closed : sans secret configure, TOUT payload est rejete.
 */
class InvalidWebhookSignatureException extends RuntimeException
{
    public function __construct(string $reason = 'signature invalide')
    {
        parent::__construct(sprintf('Webhook rejete : %s.', $reason));
    }
}
