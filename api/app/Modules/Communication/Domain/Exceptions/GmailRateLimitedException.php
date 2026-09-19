<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Exceptions;

use RuntimeException;

/**
 * Gmail a repondu 429 (quota depasse) pendant une passe de sync (R2 #7687).
 *
 * Le job de sync la capture et se re-planifie avec backoff (`release`),
 * en respectant `Retry-After` quand Google le fournit — jamais de retry
 * agressif contre l'API.
 */
class GmailRateLimitedException extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('gmail_rate_limited');
    }
}
