<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Domain\Support\CatalogSlug;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Support\Str;

/**
 * Cas d'usage « mettre a jour un produit du catalogue » (BOS-024f, #8217).
 *
 * Extrait de `CatalogProductController::update` : slug recalcule (unique par
 * tenant, id courant ignore) et invalidation de la fiche publique AVANT et
 * APRES mutation quand le slug change — l'ancienne cle doit disparaitre, la
 * nouvelle aussi (un slug libere puis repris ne doit pas servir un cache
 * perime).
 */
final class UpdateCatalogProductAction
{
    /**
     * @param  array<string, mixed>  $payload  Payload valide (UpdateCatalogProductRequest).
     */
    public function execute(CatalogProduct $product, array $payload): CatalogProduct
    {
        $companyId = (string) $product->company_id;
        $oldSlug = (string) $product->slug;

        $desiredSlug = array_key_exists('slug', $payload)
            ? (string) $payload['slug']
            : Str::slug((string) ($payload['name'] ?? ''));

        $product->update([
            'name' => $payload['name'],
            'slug' => CatalogSlug::uniqueFor(
                CatalogProduct::class,
                $companyId,
                $desiredSlug,
                (int) $product->id
            ),
            'category_id' => $payload['category_id'] ?? null,
            'description' => $payload['description'] ?? null,
            'price_minor' => (int) ($payload['price_minor'] ?? 0),
            'currency' => ($payload['currency'] ?? null) ?? $product->currency,
            'unit' => ($payload['unit'] ?? null) ?? $product->unit,
            'status' => ($payload['status'] ?? null) ?? $product->status->value,
            'meta' => $payload['meta'] ?? null,
        ]);

        $newSlug = (string) $product->refresh()->slug;

        CatalogPublicCache::forgetProduct($companyId, $oldSlug);

        if ($newSlug !== $oldSlug) {
            CatalogPublicCache::forgetProduct($companyId, $newSlug);
        }

        return $product;
    }
}
