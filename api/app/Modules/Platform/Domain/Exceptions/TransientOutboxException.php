<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Exceptions;

/**
 * #5866 — Erreur transitoire d'un consommateur d'outbox plateforme (MAT-008).
 *
 * À lever quand l'effet métier peut réussir après nouvel essai (provider
 * indisponible, timeout…). Le dispatcher re-tente avec backoff exponentiel,
 * borné par MAX_ATTEMPTS (au-delà : dead-letter).
 */
final class TransientOutboxException extends \RuntimeException
{
}
