<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Exceptions;

use DomainException;

/**
 * GSI-2 (#8513) — transition d'état impossible dans le workflow SEO.
 *
 * Couvre les deux refus du domaine :
 *  - transition non prévue par la machine à états (ex. `detected → applied`,
 *    qui permettrait d'appliquer une correction sans approbation humaine) ;
 *  - écartement sans justification.
 *
 * Le contrôleur la traduit en 422 avec un code stable (`invalid_transition`)
 * pour que le front propose l'action réellement possible.
 */
final class InvalidTransitionException extends DomainException
{
    public function errorCode(): string
    {
        return 'invalid_transition';
    }
}
