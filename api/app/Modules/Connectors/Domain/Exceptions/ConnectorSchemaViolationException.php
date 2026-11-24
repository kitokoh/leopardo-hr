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
        // `__()` ne peut PAS etre une valeur par defaut de parametre : PHP
        // refuse tout appel de fonction dans une expression constante
        // (`Constant expression contains invalid operations`, fatal au
        // chargement de la classe). On resout le libelle dans le corps.
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
