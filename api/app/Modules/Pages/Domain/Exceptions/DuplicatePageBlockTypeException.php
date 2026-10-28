<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * #8724 — deux BC déclarent le même identifiant de bloc.
 *
 * Erreur de configuration détectée au boot (construction du registre), pas une
 * erreur de requête : d'où un 500 côté HTTP si elle remonte, ce qui est le bon
 * signal — un registre incohérent ne doit jamais servir de contenu public.
 *
 * On ne remplace JAMAIS silencieusement un type existant : le dernier
 * enregistré gagnerait, et un contenu validé contre un schéma serait rendu par
 * le renderer d'un autre BC.
 */
final class DuplicatePageBlockTypeException extends DomainException
{
    public const CODE = 'PAGE_BLOCK_TYPE_DUPLICATE';

    public static function between(string $typeId, string $existingOwner, string $newOwner): self
    {
        return new self(
            __('pages.block_type_duplicate', ['type' => $typeId, 'owner' => $existingOwner, 'new' => $newOwner]),
            500,
            self::CODE,
        );
    }
}
