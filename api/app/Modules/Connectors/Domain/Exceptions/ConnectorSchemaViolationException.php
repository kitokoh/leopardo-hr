<?php

declare(strict_types=1);

namespace App\Modules\Connectors\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #9846 (BC-14 INTEGRATION) — la saisie ne respecte pas le schéma du catalogue.
 *
 * Hérite de `App\Exceptions\DomainException` (et NON du `\DomainException`
 * global) : c'est la seule classe que le renderer de `bootstrap/app.php`
 * traduit en réponse de contrat (#8247). Une sous-classe du global produirait
 * un 500 alors que la traduction existe — leçon #9661.
 *
 * Le code d'erreur est stable et sans placeholder : le renderer ne passe aucun
 * paramètre à `__()`.
 */
class ConnectorSchemaViolationException extends DomainException
{
    public const ERROR_CODE = 'CONNECTOR_SCHEMA_VIOLATION';

    /**
     * @param  list<string>  $violations  messages lisibles, un par champ fautif
     */
    public function __construct(
        private readonly array $violations,
        ?string $message = null,
    ) {
        // #eval-2026-11-23 — `__()` n'est pas une expression constante : le
        // l'utiliser comme valeur par defaut provoquait un fatal
        // « Constant expression contains invalid operations » des que la
        // classe etait chargee (toute la suite de tests tombait). La
        // traduction est donc resolue a l'execution.
        parent::__construct($message ?? __('connectors.schema_violation'), 422, self::ERROR_CODE);
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
