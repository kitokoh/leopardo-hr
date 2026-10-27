<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Exceptions;

use App\Shared\Exceptions\DomainException;

/**
 * Motifs de refus de publication d'une Page (BC PAGES, #8723).
 *
 * Exception dédiée plutôt qu'un `\DomainException` brut : le contrat HTTP
 * (statut + code machine stable) doit être décidé ICI, une seule fois, et non
 * par chaque contrôleur qui rattrape l'erreur.
 */
final class PagePublishException extends DomainException
{
    public static function missingSeoTitle(): self
    {
        return new self(
            __('pages.publish_seo_title_required'),
            422,
            'PAGE_SEO_TITLE_REQUIRED',
        );
    }
}
