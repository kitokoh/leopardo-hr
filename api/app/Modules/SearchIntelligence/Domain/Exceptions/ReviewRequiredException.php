<?php

declare(strict_types=1);

namespace App\Modules\SearchIntelligence\Domain\Exceptions;

use DomainException;

/**
 * GSI-4 (#8515) — tentative de publication d'une réponse AEO sans révision
 * humaine valide.
 *
 * Exception de domaine : elle signale un état de programmation incorrect, pas
 * une erreur de saisie utilisateur. Le contrôleur la traduit en 422 avec un
 * code stable (`aeo_review_required`) pour que le front affiche l'action
 * attendue (« faire relire ») plutôt qu'un message générique.
 */
final class ReviewRequiredException extends DomainException
{
    public function errorCode(): string
    {
        return 'aeo_review_required';
    }
}
