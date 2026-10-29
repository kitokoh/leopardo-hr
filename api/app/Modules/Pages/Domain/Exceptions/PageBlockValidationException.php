<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Contenu de blocs invalide (BC PAGES, #8728).
 *
 * ## Pourquoi une exception typée et pas un `abort(422)`
 *
 * Leçon #8247 d'`AGENTS.md` : un `abort()` n'expose jamais son code au client —
 * le renderer `HttpExceptionInterface` réécrit tout 422 en message générique.
 * L'éditeur a besoin de la liste **complète** des erreurs, indexée par bloc,
 * pour les afficher ensemble.
 *
 * ## Forme du payload
 *
 * `errors` est indexé par position de bloc (`0`, `1`, …) et non par
 * `blocks.0.content.title` : l'éditeur affiche l'erreur à côté du bloc
 * concerné, pas à côté d'un champ de formulaire HTML.
 *
 * Le contrôleur mappe cette exception en `422` avec le payload `errors` — le
 * renderer générique `DomainException` ne transporte pas de contexte, et
 * l'étendre pour un seul cas ferait porter à tous les domaines un contrat
 * qu'aucun autre n'utilise.
 */
final class PageBlockValidationException extends DomainException
{
    /**
     * @param  array<int, list<string>>  $errors
     */
    private function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('PAGE_BLOCKS_INVALID', 422, 'PAGE_BLOCKS_INVALID');
    }

    /**
     * @param  array<int, list<string>>  $errors
     */
    public static function withErrors(array $errors): self
    {
        return new self($errors);
    }
}
