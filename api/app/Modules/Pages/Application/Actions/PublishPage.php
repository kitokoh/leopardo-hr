<?php

declare(strict_types=1);

namespace App\Modules\Pages\Application\Actions;

use App\Modules\Pages\Domain\Exceptions\PagePublishException;
use App\Modules\Pages\Domain\Models\Page;

/**
 * Publier une Page (BC PAGES, #8723).
 *
 * Cas d'usage séparé du contrôleur : « on ne publie pas une Page sans titre
 * SEO » est une décision métier, pas une validation HTTP. Elle doit rester
 * réutilisable par un futur job de publication programmée, qui n'a pas de
 * requête à valider.
 *
 * Le titre vit dans `seo.title` et non dans une colonne dédiée : le bloc SEO
 * porte aussi la description, les images Open Graph et le canonical, et les
 * éclater en colonnes rendrait impossible l'ajout d'un champ sans migration.
 *
 * La cohérence de tenant est déléguée au modèle
 * (`assertSameTenantAsSite`) plutôt que refaite ici : deux implémentations
 * d'une même règle finissent toujours par diverger, et c'est celle du modèle
 * qui est adossée à la base.
 */
final class PublishPage
{
    public function handle(Page $page): Page
    {
        $page->assertSameTenantAsSite();

        $title = $page->seo['title'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            throw PagePublishException::missingSeoTitle();
        }

        $page->publish();
        $page->save();

        return $page;
    }
}
