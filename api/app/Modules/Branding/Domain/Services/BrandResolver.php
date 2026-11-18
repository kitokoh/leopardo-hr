<?php

declare(strict_types=1);

namespace App\Modules\Branding\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Branding\Domain\Contracts\BrandCacheInterface;
use App\Modules\Branding\Domain\Models\Brand;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;

/**
 * Résolution d'une Brand publique par slug ou par host (BC-41, #9578).
 *
 * ## Une seule porte d'entrée, un seul échec
 *
 * Le rendu public est une surface SANS authentification. Toute la logique de
 * résolution vit ici, et elle renvoie `null` dès qu'une condition manque —
 * volontairement. Un resolver qui doit distinguer six cas d'échec finit par en
 * oublier un, et l'oublié est toujours celui qui laisse fuiter du contenu.
 *
 * L'appelant traduit `null` en **404 uniforme**, jamais 403 : un 403
 * confirmerait l'existence de la ressource et permettrait d'énumérer le parc
 * client. C'est l'invariant de `PublicTenantResolver` (#8208) et on ne le
 * réimplémente pas.
 *
 * ## Pourquoi parcourir les tenants
 *
 * Il n'existe pas d'index global des Brands : la vérité vit dans le schéma de
 * chaque tenant. Créer un index global dupliquerait cette vérité et créerait
 * une seconde source à maintenir. Le coût est borné (une requête par tenant,
 * arrêtée au premier match) et le résultat est mis en cache par slug/host.
 *
 * ## Ce que le cache ne fait PAS
 *
 * Il ne cache jamais une Brand non publiée : seule une résolution réussie est
 * mémorisée, et pour une durée courte. Un cache négatif rendrait une Brand
 * invisible plusieurs minutes après sa publication — un tenant qui publie et
 * ne voit rien conclut que le produit est cassé.
 */
final class BrandResolver
{
    /** Borne du parcours de tenants (évite un 404 à plusieurs secondes). */
    private const MAX_TENANTS = 500;

    public function __construct(
        private readonly PublicTenantResolver $tenantResolver,
        private readonly BrandCacheInterface $cache,
    ) {}

    /**
     * Résout une Brand publiée par son slug.
     *
     * @return array{brand: Brand, company: Company}|null
     */
    public function bySlug(string $rawSlug): ?array
    {
        $slug = Brand::normalizeSlug($rawSlug);

        if ($slug === null) {
            return null;
        }

        $cached = $this->cache->getBySlug($slug);

        if ($cached !== null) {
            return $cached;
        }

        foreach ($this->candidateCompanies() as $company) {
            if (! $this->tenantIsAccessible($company)) {
                continue;
            }

            $brand = $this->tenantResolver->withinTenant($company, function () use ($slug): ?Brand {
                /** @var Brand|null $brand */
                $brand = Brand::query()->where('slug', $slug)->first();

                return $brand instanceof Brand && $brand->isPubliclyVisible() ? $brand : null;
            });

            if ($brand instanceof Brand) {
                $resolved = ['brand' => $brand, 'company' => $company];
                $this->cache->putBySlug($slug, $resolved);

                return $resolved;
            }
        }

        return null;
    }

    /**
     * Résout une Brand publiée par son host personnalisé.
     *
     * @return array{brand: Brand, company: Company}|null
     */
    public function byHost(string $rawHost): ?array
    {
        $host = self::normalizeHost($rawHost);

        if ($host === null) {
            return null;
        }

        $cached = $this->cache->getByHost($host);

        if ($cached !== null) {
            return $cached;
        }

        foreach ($this->candidateCompanies() as $company) {
            if (! $this->tenantIsAccessible($company)) {
                continue;
            }

            $brand = $this->tenantResolver->withinTenant($company, function () use ($host): ?Brand {
                /** @var Brand|null $brand */
                $brand = Brand::query()->where('host', $host)->first();

                return $brand instanceof Brand && $brand->isPubliclyVisible() ? $brand : null;
            });

            if ($brand instanceof Brand) {
                $resolved = ['brand' => $brand, 'company' => $company];
                $this->cache->putByHost($host, $resolved);

                return $resolved;
            }
        }

        return null;
    }

    /**
     * Normalise un hostname.
     *
     * Minuscules, sans port, sans point final. `Chez-Lea.SN:443` et
     * `chez-lea.sn` désignent le même domaine, et un `Host` forgé avec un port
     * ne doit pas contourner la résolution.
     */
    public static function normalizeHost(string $host): ?string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('/:\d+$/', '', $host);
        $host = rtrim($host, '.');

        if ($host === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9\-\.]{0,251}[a-z0-9])?$/', $host)) {
            return null;
        }

        return $host;
    }

    /**
     * Gardes d'accès publiques (société suspendue/expirée → inaccessible).
     *
     * On ne réimplémente pas la règle : on appelle le socle (#8208). Une
     * exception signifie « pas accessible », donc on passe au tenant suivant.
     */
    private function tenantIsAccessible(Company $company): bool
    {
        try {
            $this->tenantResolver->assertAccessible($company, null);

            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            return false;
        }
    }

    /**
     * Tenants candidats, bornés.
     *
     * La liste vient de `companies`, source de vérité des tenants — pas de
     * `pg_namespace`, qui contiendrait aussi des schémas techniques.
     *
     * @return list<Company>
     */
    private function candidateCompanies(): array
    {
        /** @var list<Company> $companies */
        $companies = Company::query()
            ->orderBy('created_at')
            ->limit(self::MAX_TENANTS)
            ->get()
            ->all();

        return $companies;
    }
}
