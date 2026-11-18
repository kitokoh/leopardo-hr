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
            'BRAND_PUBLISH_MISSING_DISPLAY_NAME: '.__('errors.BRAND_PUBLISH_MISSING_DISPLAY_NAME')
        );
    }

    public static function suspended(): self
    {
        return new self(
            'BRAND_PUBLISH_SUSPENDED: '.__('errors.BRAND_PUBLISH_SUSPENDED')
        );
    }
}
