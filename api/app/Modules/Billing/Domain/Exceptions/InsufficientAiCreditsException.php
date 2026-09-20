<?php

declare(strict_types=1);

namespace App\Modules\Billing\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #7764 — solde de crédits IA insuffisant pour couvrir un débit.
 *
 * Réponse API : 422 avec code stable `AI_CREDITS_EXHAUSTED` (fail-closed,
 * même contrat d'erreur que AI_QUOTA_EXCEEDED / AI_TOKEN_BUDGET_EXCEEDED :
 * `{error, message, localized_message}` via le renderer DomainException de
 * bootstrap/app.php — le code est au catalogue `errors.AI_CREDITS_EXHAUSTED`).
 */
class InsufficientAiCreditsException extends DomainException
{
    public const ERROR_CODE = 'AI_CREDITS_EXHAUSTED';

    public function __construct(string $message)
    {
        parent::__construct($message, 422, self::ERROR_CODE);
    }
}
