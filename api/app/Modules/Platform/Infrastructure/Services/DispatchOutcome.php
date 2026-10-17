<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Services;

/**
 * #8673 — résultat typé d'un `repository_dispatch`.
 *
 * Objet plutôt qu'un tableau : PHPStan niveau 8 exige la forme exacte des
 * tableaux (docblock `array{...}`) et Pint réordonne ces docblocks, ce qui
 * faisait osciller les deux gardes l'une après l'autre. Un petit objet
 * immuable supprime le problème à la racine et rend le client auto-documenté.
 */
final readonly class DispatchOutcome
{
    public function __construct(
        public int $status,
        public bool $ok,
        public ?int $id = null,
    ) {}
}
