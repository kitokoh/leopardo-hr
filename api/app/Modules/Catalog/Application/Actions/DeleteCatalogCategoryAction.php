<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;

/**
 * Cas d'usage « supprimer une categorie du catalogue » (BOS-024f, #8217).
 *
 * Extrait de `CatalogCategoryController::destroy` : invalidation du snapshot
 * public puis suppression.
 */
final class DeleteCatalogCategoryAction
{
    public function execute(CatalogCategory $category): void
    {
        CatalogPublicCache::forgetCompany((string) $category->company_id);

        $category->delete();
    }
}
