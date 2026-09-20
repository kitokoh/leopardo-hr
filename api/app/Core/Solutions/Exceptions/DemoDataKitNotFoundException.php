<?php

declare(strict_types=1);

namespace App\Core\Solutions\Exceptions;

use App\Exceptions\DomainException;

/**
 * #7865 — code de solution sans kit de démonstration enregistré
 * (fail-closed, miroir de `SolutionNotFoundException`).
 */
class DemoDataKitNotFoundException extends DomainException
{
    public function __construct(string $code)
    {
        parent::__construct(
            sprintf('Aucun kit de demonstration pour la solution "%s".', $code),
            404,
            'DEMO_DATA_KIT_NOT_FOUND'
        );
    }
}
