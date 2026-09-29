<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Support\CatalogSlug;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Support\Str;

/**
 * Cas d'usage « mettre a jour une categorie du catalogue » (BOS-024f, #8217).
 *
 * Extrait de `CatalogCategoryController::update` : slug recalcule (id courant
 * ignore), position conservee si le formulaire ne la fournit pas, snapshot
 * public invalide.
 */
final class UpdateCatalogCategoryAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (UpdateCatalogCategoryRequest).
     */
    public function execute(CatalogCategory $category, array $payload): CatalogCategory
    {
        $companyId = (string) $category->company_id;

        $desiredSlug = array_key_exists('slug', $payload)
            ? (string) $payload['slug']
            : Str::slug((string) ($payload['name'] ?? ''));

        CatalogPublicCache::forgetCompany($companyId);

        $category->update([
            'name' => $payload['name'],
            'slug' => CatalogSlug::uniqueFor(
                CatalogCategory::class,
                $companyId,
                $desiredSlug,
                (int) $category->id
            ),
            'parent_id' => $payload['parent_id'] ?? null,
            'position' => (int) ($payload['position'] ?? (int) $category->position),
        ]);

        return $category;
    }
}
