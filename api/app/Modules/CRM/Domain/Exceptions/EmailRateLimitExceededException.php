<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * Quota d'envoi email du tenant dépassé — Issue #5726.
 * Converti en HTTP 429 par le contrôleur.
 */
final class EmailRateLimitExceededException extends RuntimeException {}
