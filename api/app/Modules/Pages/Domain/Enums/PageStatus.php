<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Enums;

/**
 * Axe 1 — **publication** d'une Page (BC PAGES, #8723).
 *
 * Deux états seulement, volontairement : la vérification de propriété est un
 * axe SÉPARÉ ({@see PageVerificationStatus}). Confondre les deux est l'erreur
 * que la conception interdit explicitement
 * (`docs/design/etablissements-pages.md` §2.3 et §11.4) : une Page peut être
 * vérifiée sans être publiée (preuves validées, contenu encore en brouillon) et
 * publiée sans être vérifiée (elle apparaît alors avec la mention « non
 * vérifiée » dans l'annuaire).
 *
 * La suspension n'est pas un troisième état : elle force le retour à `draft`
 * et laisse une trace `suspended_at`/`suspension_reason` (#8729). Cela évite
 * un état « suspendu publié » qu'aucune surface publique ne saurait rendre.
 */
enum PageStatus: string
{
    case Draft = 'draft';

    case Published = 'published';

    /**
     * La Page est-elle joignable sur sa surface publique ?
     *
     * Point unique de vérité pour `/p/{slug}`, le sitemap et l'annuaire : toute
     * surface publique interroge CETTE méthode au lieu de comparer la colonne
     * à la main (un `=== 'published'` recopié finit toujours par diverger).
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
