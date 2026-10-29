<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Models\Page;

/**
 * Dépublie une Page (BC PAGES, #8728).
 *
 * ## Pourquoi une Action et pas un `$page->unpublish(); $page->save();` inline
 *
 * Le geste a une conséquence publique immédiate (la Page disparaît de
 * l'annuaire, du sitemap et de son URL). Il doit être **le même** partout :
 * route tenant, job de publication programmée, commande de maintenance. Une
 * copie inline finit toujours par diverger sur un détail — ici, l'oubli de
 * `published_at = null`, qui laisserait une date de publication sur un
 * brouillon et violerait la contrainte `pages_draft_has_no_published_date`.
 */
final class UnpublishPage
{
    public function execute(Page $page): Page
    {
        $page->unpublish();
        $page->save();

        return $page;
    }
}
