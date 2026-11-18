<?php

declare(strict_types=1);

namespace App\Modules\Branding\Application\Actions;

use App\Modules\Branding\Domain\Contracts\BrandCacheInterface;
use App\Modules\Branding\Domain\Models\Brand;

/**
 * Publie une Brand (BC-41, #9578).
 *
 * ## Pourquoi l'action purge le cache
 *
 * `BrandResolver` met en cache les résolutions réussies (60 s). Sans purge,
 * un tenant qui publie sa Brand attendrait une minute avant de la voir en
 * ligne — et conclurait que le produit est cassé. La purge est ciblée sur les
 * deux clés possibles (slug et host), pas un `flush` global qui jetterait le
 * cache de tous les autres tenants.
 *
 * ## Ce que l'action ne fait pas
 *
 * Elle ne vérifie pas les entitlements : publier une Brand n'est pas une
 * fonctionnalité facturée, c'est l'acte de mise en ligne. Les quotas de
 * domaines personnalisés, eux, sont vérifiés au moment où un host est posé.
 */
final class PublishBrandAction
{
    public function __construct(
        private readonly BrandCacheInterface $cache,
    ) {}

    public function execute(Brand $brand): Brand
    {
        $brand->publish();
        $brand->save();

        $this->forget($brand);

        return $brand;
    }

    /**
     * Purge les entrées de cache de cette Brand.
     */
    public function forget(Brand $brand): void
    {
        $this->cache->forget($brand->slug, $brand->host);
    }
}
