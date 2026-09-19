<?php

declare(strict_types=1);

namespace App\Modules\Retail\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\RetailCategory;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Support\RetailFeatures;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Issue #7807 — Marketplace publique Retail : decouverte cross-tenant.
 *
 * Agregation CROSS-TENANT volontaire et bornee (pattern
 * TravelMarketplaceService #7737) : seules les boutiques OPT-IN sont
 * visibles — opt-in = ligne `retail_online_settings` avec `enabled = true`
 * ET feature tenant `retail` active (kill switch plateforme respecte via
 * `Company::hasFeature()`). Toute requete est explicitement contrainte par
 * `whereIn('company_id', $eligibles)` : jamais de lecture non bornee.
 *
 * Visibilite publique d'un produit (fail-closed, spec §2) :
 * produit `published` ET `online_visible` ET boutique `enabled`.
 */
final class RetailMarketplaceService
{
    /**
     * Tenants opt-in : boutique en ligne activee + feature `retail`.
     *
     * @return list<string>
     */
    public function eligibleCompanyIds(): array
    {
        $companyIds = RetailOnlineSettings::query()
            ->withoutGlobalScope('company')
            ->where('enabled', true)
            ->pluck('company_id')
            ->unique()
            ->values();

        if ($companyIds->isEmpty()) {
            return [];
        }

        return array_values(Company::query()
            ->whereIn('id', $companyIds)
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature(RetailFeatures::RETAIL))
            ->map(fn (Company $company): string => (string) $company->id)
            ->values()
            ->all());
    }

    /**
     * Recherche paginee cross-tenant des produits publics des boutiques
     * opt-in. Filtres optionnels : texte (`q`), boutique (`seller` = slug),
     * categorie (nom, insensible a la casse), fourchette de prix (minor
     * units), tri `price_asc|price_desc|newest` (defaut : nom).
     *
     * @return LengthAwarePaginator<int, RetailProduct>
     */
    public function searchProducts(
        ?string $q,
        ?string $sellerSlug,
        ?string $category,
        ?int $minPriceMinor,
        ?int $maxPriceMinor,
        ?string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $eligible = $this->eligibleCompanyIds();

        if ($sellerSlug !== null && $sellerSlug !== '') {
            $settings = $this->findEnabledSettingsBySlug($sellerSlug);

            $eligible = $settings instanceof RetailOnlineSettings
                && in_array((string) $settings->company_id, $eligible, true)
                ? [(string) $settings->company_id]
                : [];
        }

        $query = $this->publicProductsQuery($eligible);

        if ($q !== null && $q !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('name', 'ilike', '%'.$q.'%')
                ->orWhere('description', 'ilike', '%'.$q.'%'));
        }

        if ($category !== null && $category !== '') {
            $categoryIds = RetailCategory::query()
                ->withoutGlobalScope('company')
                ->whereIn('company_id', $eligible)
                ->where('name', 'ilike', $category)
                ->pluck('id');

            $query->whereIn('category_id', $categoryIds);
        }

        if ($minPriceMinor !== null) {
            $query->where('price_minor', '>=', $minPriceMinor);
        }

        if ($maxPriceMinor !== null) {
            $query->where('price_minor', '<=', $maxPriceMinor);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price_minor')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_minor')->orderBy('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderBy('name')->orderBy('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * Produit public d'une boutique eligible (resolution tenant PAR PRODUIT)
     * — null sinon : la marketplace ne sert JAMAIS un produit d'un tenant
     * non opt-in ou non visible en ligne (fail-closed 404 cote controleur).
     */
    public function findEligibleProduct(int $productId): ?RetailProduct
    {
        $eligible = $this->eligibleCompanyIds();

        if ($eligible === []) {
            return null;
        }

        /** @var RetailProduct|null $product */
        $product = $this->publicProductsQuery($eligible)->find($productId);

        return $product;
    }

    /**
     * Annuaire public des boutiques opt-in (slug, nom d'affichage,
     * description, nombre de produits en ligne — aucune donnee interne).
     *
     * @return list<RetailOnlineSettings>
     */
    public function sellers(): array
    {
        $eligible = $this->eligibleCompanyIds();

        if ($eligible === []) {
            return [];
        }

        return array_values(RetailOnlineSettings::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('enabled', true)
            ->orderBy('display_name')
            ->get()
            ->all());
    }

    /**
     * Boutique opt-in par slug public — null si inconnue, desactivee ou
     * feature retail coupee (fail-closed 404 cote controleur).
     */
    public function findEligibleSellerBySlug(string $slug): ?RetailOnlineSettings
    {
        $settings = $this->findEnabledSettingsBySlug($slug);

        if (! $settings instanceof RetailOnlineSettings) {
            return null;
        }

        return in_array((string) $settings->company_id, $this->eligibleCompanyIds(), true)
            ? $settings
            : null;
    }

    /**
     * Nombre de produits publiquement visibles d'une boutique.
     */
    public function publicProductCount(RetailOnlineSettings $settings): int
    {
        return $this->publicProductsQuery([(string) $settings->company_id])->count();
    }

    private function findEnabledSettingsBySlug(string $slug): ?RetailOnlineSettings
    {
        /** @var RetailOnlineSettings|null $settings */
        $settings = RetailOnlineSettings::query()
            ->withoutGlobalScope('company')
            ->where('slug', $slug)
            ->where('enabled', true)
            ->first();

        return $settings;
    }

    /**
     * Requete de base des produits publics : bornee aux tenants eligibles,
     * produit `published` ET `online_visible` (spec §2 — fail-closed :
     * liste eligible vide => aucune ligne).
     *
     * @param  list<string>  $eligibleCompanyIds
     * @return Builder<RetailProduct>
     */
    private function publicProductsQuery(array $eligibleCompanyIds): Builder
    {
        $query = RetailProduct::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligibleCompanyIds)
            ->where('status', RetailProductStatus::Published->value)
            ->where('online_visible', true);

        if ($eligibleCompanyIds === []) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
