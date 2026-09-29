<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;

/**
 * Cas d'usage « publier / depublier un produit » (BOS-024f, #8217).
 *
 * Extrait de `CatalogProductController::setStatus` (endpoints `publish` et
 * `unpublish`) : le statut est porte par l'enum, le cache public de la fiche
 * est purge a chaque bascule — un produit depublie ne doit jamais rester
 * servi depuis Redis.
 */
final class TransitionCatalogProductStatusAction
{
    public function execute(CatalogProduct $product, CatalogProductStatus $status): CatalogProduct
    {
        CatalogPublicCache::forgetProduct((string) $product->company_id, (string) $product->slug);

        $product->update(['status' => $status->value]);

        return $product->refresh();
    }
}
