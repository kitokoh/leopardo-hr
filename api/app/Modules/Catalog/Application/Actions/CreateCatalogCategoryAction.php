<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Support\CatalogSlug;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Support\Str;

/**
 * Cas d'usage « creer une categorie du catalogue » (BOS-024f, #8217).
 *
 * Extrait de `CatalogCategoryController::store` : derivation serveur du slug
 * unique par tenant puis invalidation du snapshot public (une categorie
 * apparait dans la liste publique).
 */
final class CreateCatalogCategoryAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (StoreCatalogCategoryRequest).
     */
    public function execute(string $companyId, array $payload): CatalogCategory
    {
        $desiredSlug = array_key_exists('slug', $payload)
            ? (string) $payload['slug']
            : Str::slug((string) ($payload['name'] ?? ''));

        /** @var CatalogCategory $category */
        $category = CatalogCategory::query()->create([
            'company_id' => $companyId,
            'name' => $payload['name'],
            'slug' => CatalogSlug::uniqueFor(CatalogCategory::class, $companyId, $desiredSlug),
            'parent_id' => $payload['parent_id'] ?? null,
            'position' => (int) ($payload['position'] ?? 0),
        ]);

        CatalogPublicCache::forgetCompany($companyId);

        return $category;
    }
}
