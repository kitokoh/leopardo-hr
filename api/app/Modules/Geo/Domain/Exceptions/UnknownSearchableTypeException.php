<?php

declare(strict_types=1);

namespace App\Modules\Geo\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * GEO-04 (#8353, BC-33 GEO) — type recherchable inconnu (fail-closed).
 *
 * La registry `geo.searchables` est opt-in : interroger `/geo/nearest` avec
 * un type non enregistré est refusé (aucune donnée tenant n'est exposée sans
 * enregistrement explicite, règle spec §7).
 */
class UnknownSearchableTypeException extends DomainException
{
    public function __construct(string $type)
    {
        parent::__construct(
            "Type recherchable inconnu : {$type}. Enregistrer le type dans geo.searchables (opt-in).",
            422,
            'GEO_UNKNOWN_SEARCHABLE_TYPE'
        );
    }
}
