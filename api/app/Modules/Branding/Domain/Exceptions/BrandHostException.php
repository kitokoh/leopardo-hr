<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Le domaine personnalisé d'une Brand est invalide (BC-41, #9578).
 *
 * ## Pourquoi refuser au lieu de « ne rien poser »
 *
 * `BrandResolver::normalizeHost()` renvoie `null` pour un hostname
 * inexploitable. Les actions de création et de mise à jour traitaient ce
 * `null` comme « pas de domaine » :
 *
 * - à la création, un tenant qui saisissait « chez léa.sn » obtenait une Brand
 *   **sans** domaine, sans erreur — la surface publique n'était jamais
 *   résolvable par host et personne ne comprenait pourquoi ;
 * - à la mise à jour, la même saisie **effaçait** le domaine existant : une
 *   faute de frappe détruisait une donnée de production, en silence.
 *
 * Un `null` de normalisation n'est pas une intention, c'est un refus. On lève,
 * et le client reçoit un 422 localisé qui lui dit quoi corriger.
 */
final class BrandHostException extends DomainException
{
    public static function invalid(string $raw): self
    {
        return new self(
            sprintf(
                'BRAND_HOST_INVALID: "%s" is not a usable hostname '
                .'(expected: lowercase letters, digits, hyphen and dot, 1..253 characters).',
                $raw,
            ),
            422,
            'BRAND_HOST_INVALID',
        );
    }
}
