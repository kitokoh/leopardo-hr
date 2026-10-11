<?php

declare(strict_types=1);

namespace App\Modules\Geo\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * #8423 (M-02, BC-34 GEO) — type recherché réservé à certains rôles.
 *
 * Un type enregistré avec une garde d'autorisation (ex. `vtc_driver` :
 * positions des chauffeurs, réservées admin/dispatcher de la verticale)
 * refuse la recherche HTTP `GET /v1/geo/nearest` aux autres profils — 403
 * fail-closed, code catalogué `errors.GEO_SEARCHABLE_TYPE_FORBIDDEN`
 * (localized_message localisé par le renderer DomainException).
 */
class SearchableTypeForbiddenException extends DomainException
{
    public function __construct(string $type)
    {
        parent::__construct(
            "Type recherchable réservé : {$type}. Rôle insuffisant pour cette recherche de proximité.",
            403,
            'GEO_SEARCHABLE_TYPE_FORBIDDEN'
        );
    }
}
