<?php

declare(strict_types=1);

namespace App\Modules\Pages\Infrastructure\Services;

use App\Modules\Pages\Domain\Models\Page;
use App\Modules\Pages\Domain\Models\PageBlockModel;
use App\Shared\Contracts\Pages\PageProjectionReader as PageProjectionReaderContract;

/**
 * Implémentation Pages du contrat {@see PageProjectionReaderContract} (#8727).
 *
 * Vit en Infrastructure : c'est le seul endroit du module Pages qui a le droit
 * de parler de ses modèles, et le consommateur (Showcase) ne voit que le
 * contrat partagé.
 *
 * La lecture est faite **sans** scope tenant explicite : l'appelant exécute
 * déjà dans `TenantManager::withinTenant` (la surface publique Showcase), donc
 * le scope `BelongsToCompany` s'applique normalement. Ajouter un filtre ici
 * dupliquerait la règle et masquerait une éventuelle fuite de contexte.
 */
final class PageProjectionReader implements PageProjectionReaderContract
{
    public function forSite(int $siteId): ?array
    {
        /** @var Page|null $page */
        $page = Page::query()->where('site_id', $siteId)->first();

        if (! $page instanceof Page) {
            return null;
        }

        $status = $page->status instanceof \BackedEnum
            ? (string) $page->status->value
            : (string) $page->status;

        return [
            'status' => $status,
            'slug' => (string) $page->slug,
            'sections' => (int) PageBlockModel::query()->where('page_id', $page->id)->count(),
        ];
    }
}
