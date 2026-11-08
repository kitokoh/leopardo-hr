<?php

declare(strict_types=1);

namespace App\Modules\Library\Domain\Enums;

/**
 * Statut éditorial d'une ressource (workflow brouillon → validation → publié →
 * archivé). Aucune publication automatique : une ressource n'est visible
 * publiquement qu'après passage explicite à `published`.
 */
enum LibraryResourceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Archived = 'archived';

    /** Vrai si la ressource est visible sur les surfaces publiques. */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
