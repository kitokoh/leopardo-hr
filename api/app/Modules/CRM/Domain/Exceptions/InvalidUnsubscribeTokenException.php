<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Exceptions;

use RuntimeException;

/**
 * Jeton de désabonnement invalide ou expiré — Issue #5726.
 */
final class InvalidUnsubscribeTokenException extends RuntimeException {}
