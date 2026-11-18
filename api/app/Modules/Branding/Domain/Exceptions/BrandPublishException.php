<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Exceptions;

use DomainException;

/**
 * Une Brand ne peut pas être publiée en l'état (BC-41, #9578).
 *
 * Une surface publique sans nom d'enseigne n'est pas une surface : le visiteur
 * verrait un site sans identité, et les métadonnées SEO seraient vides. On
 * refuse à la publication plutôt que de laisser un rendu dégradé en ligne.
 */
final class BrandPublishException extends DomainException
{
    public static function missingDisplayName(): self
    {
        return new self(
            'BRAND_PUBLISH_MISSING_DISPLAY_NAME: une Brand doit avoir un nom public '
            .'(identity.display_name) avant d\'être publiée.'
        );
    }

    public static function suspended(): self
    {
        return new self(
            'BRAND_PUBLISH_SUSPENDED: une Brand suspendue ne peut pas être publiée. '
            .'Levez d\'abord la suspension (retour en brouillon), puis republiez.'
        );
    }
}
