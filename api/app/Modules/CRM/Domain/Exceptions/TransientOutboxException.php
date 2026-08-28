<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * #5741 — Erreur TRANSITOIRE d'un consommateur d'outbox : le retry avec
 * backoff est approprié (provider indisponible, timeout, 5xx).
 */
class TransientOutboxException extends RuntimeException
{
}
