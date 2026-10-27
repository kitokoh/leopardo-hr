<?php

declare(strict_types=1);

namespace App\Modules\Pages\Infrastructure\Repositories;

use App\Modules\Pages\Domain\Models\Page;

/**
 * Accès persistant aux Pages (BC PAGES, #8723).
 *
 * Point d'entrée unique des lectures qui ne sont pas de simples `find()` :
 * centraliser ici évite que chaque surface publique réinvente son propre
 * filtre de visibilité et laisse fuir une Page suspendue.
 *
 * Le filtre `published()` vit dans la requête, jamais chez l'appelant : un
 * contrôleur qui oublie de vérifier le statut ne doit pas pouvoir servir du
 * contenu retiré.
 */
final class EloquentPageRepository
{
    /**
     * Pages publiées d'un Site, prêtes pour le rendu public.
     *
     * @return list<Page>
     */
    public function publishedForSite(string $siteId): array
    {
        /** @var list<Page> $pages */
        $pages = Page::query()
            ->where('site_id', $siteId)
            ->published()
            ->orderBy('slug')
            ->get()
            ->all();

        return $pages;
    }

    /**
     * Résolution publique par slug : ne renvoie QUE du contenu publiable.
     *
     * Une Page suspendue a `status = draft` **et** `suspended_at` renseigné ;
     * le scope `published()` filtre les deux, donc une suspension en cours de
     * traitement ne réapparaît pas entre deux déploiements.
     */
    public function findPublishedBySlug(string $slug): ?Page
    {
        /** @var Page|null $page */
        $page = Page::query()
            ->where('slug', $slug)
            ->published()
            ->first();

        return $page;
    }
}
