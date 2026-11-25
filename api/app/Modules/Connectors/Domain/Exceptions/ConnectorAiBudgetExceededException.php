<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #9925 (BC-14 INTEGRATION) — le budget mensuel de tokens d'une connexion IA
 * est atteint.
 *
 * Hérite de `App\Exceptions\DomainException` (et NON du `\DomainException`
 * global) : c'est la seule classe que le renderer de `bootstrap/app.php`
 * traduit en réponse de contrat (#8247). Une sous-classe du global produirait
 * un 500 alors que la traduction existe — leçon #9661.
 *
 * Le code d'erreur est stable et sans placeholder : le renderer ne passe aucun
 * paramètre à `__()`.
 */
class ConnectorAiBudgetExceededException extends DomainException
{
    public const ERROR_CODE = 'CONNECTOR_AI_BUDGET_EXCEEDED';

    public function __construct(
        private readonly int $usedTokens,
        private readonly int $budgetTokens,
    ) {
        parent::__construct(
            sprintf(
                'Budget mensuel de tokens atteint (%d / %d). Augmentez le budget de la connexion ou attendez le mois prochain.',
                $usedTokens,
                $budgetTokens,
            ),
            422,
            self::ERROR_CODE,
        );
    }

    public function usedTokens(): int
    {
        return $this->usedTokens;
    }

    public function budgetTokens(): int
    {
        return $this->budgetTokens;
    }
}