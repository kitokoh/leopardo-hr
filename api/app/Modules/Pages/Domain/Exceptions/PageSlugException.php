<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Slug de Page inutilisable ou réservé (BC PAGES, #8728).
 *
 * Deux causes distinctes, deux codes distincts : l'utilisateur doit savoir
 * s'il doit **changer de nom** (rien d'exploitable ne reste après
 * translittération) ou **choisir un autre nom** (celui-ci est réservé par la
 * plateforme). Un message unique « slug invalide » ne lui dit pas quoi faire.
 */
final class PageSlugException extends DomainException
{
    private function __construct(string $code, string $message)
    {
        parent::__construct($message, 422, $code);
    }

    public static function unusable(): self
    {
        return new self(
            'PAGE_SLUG_UNUSABLE',
            'Le nom fourni ne permet pas de construire une adresse publique. Utilisez des lettres et des chiffres.',
        );
    }

    public static function reserved(string $slug): self
    {
        return new self(
            'PAGE_SLUG_RESERVED',
            sprintf('L\'adresse « %s » est réservée par la plateforme. Choisissez-en une autre.', $slug),
        );
    }

    public static function alreadyTaken(string $slug): self
    {
        return new self(
            'PAGE_SLUG_TAKEN',
            sprintf('L\'adresse « %s » est déjà utilisée.', $slug),
        );
    }
}
