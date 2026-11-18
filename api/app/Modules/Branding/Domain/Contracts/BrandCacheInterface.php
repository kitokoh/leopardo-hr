<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Contracts;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Branding\Domain\Models\Brand;

/**
 * Cache des résolutions publiques de Brand (BC-41, #9578).
 *
 * ## Pourquoi un contrat et pas la facade
 *
 * `BrandResolver` vit dans le Domain et `PublishBrandAction` dans
 * l'Application : ni l'un ni l'autre ne touche une facade Laravel (garde
 * `check-layer-purity.sh`, issue #6568). Le cache est un détail
 * d'infrastructure — il est donc derrière ce contrat, implémenté par
 * `Infrastructure\Services\BrandCache`.
 *
 * ## Ce que le cache ne fait PAS
 *
 * Il ne mémorise jamais une résolution négative : seule une Brand publiée
 * est mise en cache, et pour une durée courte. Un cache négatif rendrait une
 * Brand invisible plusieurs minutes après sa publication.
 */
interface BrandCacheInterface
{
    /**
     * @return array{brand: Brand, company: Company}|null
     */
    public function getBySlug(string $slug): ?array;

    /**
     * @param  array{brand: Brand, company: Company}  $resolved
     */
    public function putBySlug(string $slug, array $resolved): void;

    /**
     * @return array{brand: Brand, company: Company}|null
     */
    public function getByHost(string $host): ?array;

    /**
     * @param  array{brand: Brand, company: Company}  $resolved
     */
    public function putByHost(string $host, array $resolved): void;

    /**
     * Purge les deux entrées possibles (slug et host) d'une Brand.
     */
    public function forget(string $slug, ?string $host): void;
}
