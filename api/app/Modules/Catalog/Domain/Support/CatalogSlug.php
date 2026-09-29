<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Regle de domaine « slug unique par tenant » (BC-28 CATALOG, #6881).
 *
 * Extrait des controllers `CatalogProductController` / `CatalogCategoryController`
 * (BOS-024f, #8217) : une collision de slug est resolue par un suffixe numerique
 * (`-2`, `-3`, ...). L'id en cours d'edition est ignore, sinon une mise a jour
 * sans changement de slug entrerait en collision avec elle-meme.
 */
final class CatalogSlug
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function uniqueFor(string $model, string $companyId, string $slug, int $ignoreId = 0): string
    {
        $candidate = $slug;
        $suffix = 2;

        while ($model::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->where('id', '!=', $ignoreId)
            ->exists()
        ) {
            $candidate = $slug.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
