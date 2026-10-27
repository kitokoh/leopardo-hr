<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\CustomDomains;

/**
 * Résultat d'une analyse de domaine : host normalisé + diagnostic.
 *
 * Objet plutôt que tableau : le tableau finit toujours par être indexé par
 * une clé mal orthographiée (`'apex'` vs `'is_apex'`) et personne ne le voit
 * avant la production.
 */
final readonly class DomainInspection
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $host,
        public bool $valid,
        public bool $isApex,
        public bool $isWildcard,
        public array $errors,
    ) {}

    /**
     * Méthode ET propriété auraient le même nom en PHP (`$o->valid()` vs
     * `$o->valid`) : c'est légal, mais illisible. On garde la propriété `valid`
     * et on expose `isValid()` comme le seul nom à retenir côté appelant.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    public function errorSummary(): string
    {
        return implode(' ; ', $this->errors);
    }
}
