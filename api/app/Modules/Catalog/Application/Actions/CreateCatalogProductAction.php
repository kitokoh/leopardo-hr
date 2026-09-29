<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Domain\Support\CatalogSlug;
use App\Modules\Catalog\Infrastructure\Services\CatalogPublicCache;
use Illuminate\Support\Str;

/**
 * Cas d'usage « creer un produit du catalogue » (BOS-024f, #8217 — BC-28).
 *
 * Extrait de `CatalogProductController::store` : derivation serveur du slug
 * (unique par tenant) et devise par defaut du tenant quand le formulaire n'en
 * fournit pas (C-CURRENCY #6886), puis invalidation du cache public. Aucune
 * evolution de comportement ni de contrat d'API.
 */
final class CreateCatalogProductAction
{
    /** Unite par defaut d'un produit (spec §8). */
    public const DEFAULT_UNIT = 'piece';

    /**
     * @param  array<string, mixed>  $payload  Payload valide (StoreCatalogProductRequest).
     */
    public function execute(string $companyId, array $payload, string $defaultCurrency): CatalogProduct
    {
        $desiredSlug = array_key_exists('slug', $payload)
            ? (string) $payload['slug']
            : Str::slug((string) ($payload['name'] ?? ''));

        /** @var CatalogProduct $product */
        $product = CatalogProduct::query()->create([
            'company_id' => $companyId,
            'category_id' => $payload['category_id'] ?? null,
            'name' => $payload['name'],
            'slug' => CatalogSlug::uniqueFor(CatalogProduct::class, $companyId, $desiredSlug),
            'description' => $payload['description'] ?? null,
            'price_minor' => (int) ($payload['price_minor'] ?? 0),
            'currency' => $payload['currency'] ?? $defaultCurrency,
            'unit' => array_key_exists('unit', $payload) ? $payload['unit'] : self::DEFAULT_UNIT,
            'status' => array_key_exists('status', $payload)
                ? $payload['status']
                : CatalogProductStatus::Draft->value,
            'meta' => $payload['meta'] ?? null,
        ]);

        CatalogPublicCache::forgetCompany($companyId);

        return $product;
    }
}
