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
            if ($this->isServable($cached)) {
                return $cached;
            }

            // Entrée périmée : la Brand a été suspendue ou dépubliée hors des
            // actions qui purgent (modération directe en base, autre worker,
            // rejeu), ou le tenant est devenu inaccessible (société suspendue
            // ou expirée). Le TTL de 60 s ne doit pas prolonger une décision de
            // modération : on jette l'entrée et on re-résout.
            $this->cache->forget($slug, $cached['brand']->host);
        }

        foreach ($this->candidateCompanies() as $company) {
            if (! $this->tenantIsAccessible($company)) {
                continue;
            }

            $brand = $this->brandInTenant($company, 'slug', $slug);

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
            if ($this->isServable($cached)) {
                return $cached;
            }

            // Voir bySlug() : une entrée de cache ne vaut pas décision de
            // modération (suspension) ni changement de statut du tenant.
            $this->cache->forget($cached['brand']->slug, $host);
        }

        foreach ($this->candidateCompanies() as $company) {
            if (! $this->tenantIsAccessible($company)) {
                continue;
            }

            $brand = $this->brandInTenant($company, 'host', $host);

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
     * Délègue à l'agrégat : le format d'un host est un attribut de la Brand,
     * exactement comme le slug (`Brand::normalizeSlug`). La méthode est
     * conservée ici pour ne pas casser les appelants existants.
     */
    public static function normalizeHost(string $host): ?string
    {
        return Brand::normalizeHost($host);
    }

    /**
     * Une entrée de cache est-elle encore servable ?
     *
     * Le cache mémorise une résolution RÉUSSIE, donc un couple (Brand, tenant)
     * dont l'un comme l'autre peut avoir changé depuis : la Brand peut avoir été
     * suspendue, la société peut avoir été suspendue ou expirée. Une résolution
     * mise en cache n'est pas une autorisation ; elle doit être revalidée à
     * chaque lecture.
     *
     * Les deux contrôles sont en mémoire (statut de la Brand, statut de la
     * société) : ils ne coûtent aucune requête dans le cas nominal.
     *
     * Le paramètre est typé `array<string, mixed>` et non avec la forme exacte :
     * une entrée de cache vient d'un sérialiseur et d'un driver interchangeable,
     * donc sa forme n'est pas garantie par le type statique. La valider est le
     * travail de cette méthode (`instanceof` + clés absentes), pas une
     * redondance que PHPStan pourrait supprimer.
     *
     * @param  array<string, mixed>  $resolved
     */
    private function isServable(array $resolved): bool
    {
        $brand = $resolved['brand'] ?? null;
        $company = $resolved['company'] ?? null;

        return $brand instanceof Brand
            && $company instanceof Company
            && $brand->isPubliclyVisible()
            && $this->tenantIsAccessible($company);
    }

    /**
     * Cherche la Brand visible d'un tenant, sans jamais faire tomber la surface.
     *
     * L'échec est isolé PAR TENANT : un schéma qui ne porte pas encore la table
     * `brands` (migration non jouée), une connexion qui refuse, un JSON illisible
     * ne doivent pas transformer une surface publique en 500 — et, comme la
     * boucle s'arrête au premier match, un seul tenant en retard placé avant les
     * autres rendait leurs Brands injoignables. On passe au suivant.
     *
     * Le repli reste muet côté client : l'appelant traduit `null` en 404
     * uniforme, jamais en détail d'échec (anti-énumération).
     */
    private function brandInTenant(Company $company, string $column, string $value): ?Brand
    {
        try {
            return $this->tenantResolver->withinTenant($company, function () use ($column, $value): ?Brand {
                /** @var Brand|null $brand */
                $brand = Brand::query()->where($column, $value)->first();

                return $brand instanceof Brand && $brand->isPubliclyVisible() ? $brand : null;
            });
        } catch (\Throwable) {
            return null;
        }
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
