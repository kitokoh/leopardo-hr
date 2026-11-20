<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use App\Exceptions\DomainException;

/**
 * Une Brand ne peut pas être publiée en l'état (BC-41, #9578).
 *
 * Une surface publique sans nom d'enseigne n'est pas une surface : le visiteur
 * verrait un site sans identité, et les métadonnées SEO seraient vides. On
 * refuse à la publication plutôt que de laisser un rendu dégradé en ligne.
 *
 * ## Un refus métier est un 422, pas un 500
 *
 * Le renderer du projet n'écoute que `App\Exceptions\DomainException`
 * (#8247) : avec le `\DomainException` global, publier une Brand sans
 * `identity.display_name` renvoyait « Server Error » à un tenant qui avait
 * simplement oublié un champ. Le message était pourtant déjà traduit dans les
 * 4 catalogues. L'héritage est donc aligné sur le contrat d'erreur de l'API.
 */
final class BrandPublishException extends DomainException
{
    public static function missingDisplayName(): self
    {
        return new self(
            'BRAND_PUBLISH_MISSING_DISPLAY_NAME: a Brand must have a public name '
            .'(identity.display_name) before it can be published.',
            422,
            'BRAND_PUBLISH_MISSING_DISPLAY_NAME',
        );
    }

    public static function suspended(): self
    {
        return new self(
            'BRAND_PUBLISH_SUSPENDED: a suspended Brand cannot be published. '
            .'Lift the suspension (back to draft) first, then publish again.',
            422,
            'BRAND_PUBLISH_SUSPENDED',
        );
    }
}
