<?php

declare(strict_types=1);

namespace App\AI\Exceptions;

use App\Exceptions\DomainException;

/**
 * BC-23-D10 (issue #6238) — un budget de tokens AI est dépassé.
 *
 * Réponse API : 422 avec code stable `AI_TOKEN_BUDGET_EXCEEDED` (fail-closed).
 * Le message interne (détails de service) ne doit JAMAIS fuiter vers le
 * client : il est conservé en logs / audit via le renderer dédié
 * (bootstrap/app.php) et le AIAuditLogger.
 */
class TokenBudgetExceededException extends DomainException
{
    public const ERROR_CODE = 'AI_TOKEN_BUDGET_EXCEEDED';

    public function __construct(string $message)
    {
        parent::__construct($message, 422, self::ERROR_CODE);
    }
}
