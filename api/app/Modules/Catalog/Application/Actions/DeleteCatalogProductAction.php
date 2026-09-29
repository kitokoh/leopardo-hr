<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;

/**
 * Cas d'usage « supprimer un produit du catalogue » (BOS-024f, #8217).
 *
 * Extrait de `CatalogProductController::destroy` : purge de la fiche publique
 * puis suppression. L'appelant (couche HTTP) reste responsable du contrat de
 * reponse.
 */
final class DeleteCatalogProductAction
{
    public function execute(CatalogProduct $product): void
    {
        CatalogPublicCache::forgetProduct((string) $product->company_id, (string) $product->slug);

        $product->delete();
    }
}
