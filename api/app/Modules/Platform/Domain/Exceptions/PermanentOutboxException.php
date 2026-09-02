<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Exceptions;

/**
 * #5866 — Erreur permanente d'un consommateur d'outbox plateforme (MAT-008).
 *
 * À lever quand l'effet métier ne pourra jamais réussir (payload invalide,
 * invariant violé). Le dispatcher dead-letter immédiatement : l'événement
 * reste inspectable (status failed + last_error) et rejouable via
 * `platform:outbox-replay` après correction.
 */
final class PermanentOutboxException extends \RuntimeException
{
}
