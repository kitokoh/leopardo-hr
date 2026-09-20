<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Retail\Domain\Enums\MarketplaceReviewStatus;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\MarketplaceReview;
use App\Modules\Retail\Domain\Models\RetailOnlineSettings;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Domain\Support\RetailFeatures;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * BC-17 RETAIL (#7807) — Agregation publique cross-tenant de la marketplace
 * Leopardo Marche.
 *
 * Agregation CROSS-TENANT volontaire et bornee (pattern marketplace
 * TravelAgency #7737) : seuls les vendeurs OPT-IN sont visibles — opt-in =
 * boutique en ligne activee (`retail_online_settings.enabled`) ET feature
 * flag `retail` actif ET company non suspendue/expiree. Toute requete est
 * explicitement contrainte par `whereIn('company_id', $eligibles)` : jamais
 * de lecture non bornee.
 *
 * Visibilite produit (spec §2.2) : `status = published` ET
 * `online_visible = true` ET boutique du tenant activee. Aucune quantite de
 * stock n'est exposee — uniquement `available: bool` (somme des niveaux > 0).
 */
final class RetailMarketplaceService
{
    /**
     * Tenants opt-in : boutique activee + feature `retail` + statut valide.
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
            ->whereNotIn('status', ['suspended', 'expired'])
            ->get()
            ->filter(fn (Company $company): bool => $company->hasFeature(RetailFeatures::RETAIL))
            ->map(fn (Company $company): string => (string) $company->id)
            ->values()
            ->all());
    }

    /**
     * Recherche agregee cross-tenant des produits publies + visibles en
     * ligne des vendeurs opt-in.
     *
     * @param  list<string>  $eligible
     * @return LengthAwarePaginator<int, RetailProduct>
     */
    public function searchProducts(
        array $eligible,
        ?string $q,
        ?string $sellerSlug,
        ?int $categoryId,
        ?int $minPriceMinor,
        ?int $maxPriceMinor,
        string $sort,
        int $perPage,
    ): LengthAwarePaginator {
        $query = RetailProduct::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('status', RetailProductStatus::Published->value)
            ->where('online_visible', true);

        if ($eligible === []) {
            $query->whereRaw('1 = 0');
        }

        if ($sellerSlug !== null && $sellerSlug !== '') {
            $sellerId = $this->eligibleCompanyIdForSlug($eligible, $sellerSlug);

            if ($sellerId === null) {
                // Slug inconnu ou vendeur non opt-in : aucun resultat
                // (fail-closed, jamais de repli sur tous les vendeurs).
                $query->whereRaw('1 = 0');
            } else {
                $query->where('company_id', $sellerId);
            }
        }

        if ($q !== null && $q !== '') {
            $query->where(function ($builder) use ($q): void {
                $builder
                    ->where('name', 'ilike', '%'.$q.'%')
                    ->orWhere('description', 'ilike', '%'.$q.'%');
            });
        }

        if ($categoryId !== null) {
            $query->where('category_id', $categoryId);
        }

        if ($minPriceMinor !== null) {
            $query->where('price_minor', '>=', $minPriceMinor);
        }

        if ($maxPriceMinor !== null) {
            $query->where('price_minor', '<=', $maxPriceMinor);
        }

        match ($sort) {
            'price_asc' => $query->orderBy('price_minor')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price_minor')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return $query->paginate($perPage);
    }

    /**
     * Produit public d'un vendeur opt-in (resolution PAR PRODUIT) — null
     * sinon : la marketplace ne sert JAMAIS un produit d'un tenant non
     * opt-in ni un produit non publie/non visible (fail-closed 404 cote
     * controleur).
     *
     * @param  list<string>  $eligible
     */
    public function findEligibleProduct(array $eligible, int $productId): ?RetailProduct
    {
        if ($eligible === []) {
            return null;
        }

        return RetailProduct::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->where('status', RetailProductStatus::Published->value)
            ->where('online_visible', true)
            ->find($productId);
    }

    /**
     * Disponibilite publique : produits dont la SOMME des niveaux de stock
     * est strictement positive (jamais de quantite exposee — spec §3.1).
     *
     * @param  list<string>  $eligible
     * @param  list<int>  $productIds
     * @return list<int>
     */
    public function availableProductIds(array $eligible, array $productIds): array
    {
        if ($eligible === [] || $productIds === []) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = RetailStockLevel::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $eligible)
            ->whereIn('product_id', $productIds)
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 0')
            ->pluck('product_id')
            ->values()
            ->all();

        return $ids;
    }

    /**
     * Reglages boutique des vendeurs opt-in, indexes par company_id.
     *
     * @param  list<string>  $companyIds
     * @return array<string, RetailOnlineSettings>
     */
    public function settingsByCompanyId(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return RetailOnlineSettings::query()
            ->withoutGlobalScope('company')
            ->whereIn('company_id', $companyIds)
            ->get()
            ->keyBy(fn (RetailOnlineSettings $settings): string => (string) $settings->company_id)
            ->all();
    }

    /**
     * Companies (slug public uniquement cote DTO), indexees par id.
     *
     * @param  list<string>  $companyIds
     * @return array<string, Company>
     */
    public function companiesById(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        return Company::query()
            ->whereIn('id', $companyIds)
            ->get()
            ->keyBy(fn (Company $company): string => (string) $company->id)
            ->all();
    }

    /**
     * Company d'un vendeur opt-in resolue par slug public (checkout invite,
     * filtre `seller`) — null si slug inconnu ou vendeur non eligible.
     *
     * @param  list<string>  $eligible
     */
    public function eligibleCompanyForSlug(array $eligible, string $slug): ?Company
    {
        if ($eligible === [] || $slug === '') {
            return null;
        }

        /** @var Company|null $company */
        $company = Company::query()->where('slug', $slug)->first();

        if (! $company instanceof Company
            || ! in_array((string) $company->id, $eligible, true)) {
            return null;
        }

        return $company;
    }

    /**
     * @param  list<string>  $eligible
     */
    private function eligibleCompanyIdForSlug(array $eligible, string $slug): ?string
    {
        $company = $this->eligibleCompanyForSlug($eligible, $slug);

        return $company instanceof Company ? (string) $company->id : null;
    }

    /**
     * Agregats de notation PRODUIT (avis approuves uniquement, #7814) :
     * moyenne arrondie a 1 decimale + nombre d'avis, indexes par
     * product_id. Calcul a la volee, borne aux produits demandes.
     *
     * @param  list<int>  $productIds
     * @return array<int, array{rating_avg: float, rating_count: int}>
     */
    public function productRatings(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $ratings = [];

        /** @var list<object{product_id: int|string, rating_avg: string|float|null, rating_count: int|string}> $rows */
        $rows = MarketplaceReview::query()
            ->whereIn('product_id', $productIds)
            ->where('status', MarketplaceReviewStatus::Approved->value)
            ->selectRaw('product_id, AVG(rating) AS rating_avg, COUNT(*) AS rating_count')
            ->groupBy('product_id')
            ->get()
            ->all();

        foreach ($rows as $row) {
            $ratings[(int) $row->product_id] = [
                'rating_avg' => round((float) $row->rating_avg, 1),
                'rating_count' => (int) $row->rating_count,
            ];
        }

        return $ratings;
    }

    /**
     * Agregats de notation BOUTIQUE (avis approuves uniquement, #7814) :
     * moyenne arrondie a 1 decimale + nombre d'avis, indexes par
     * company_id (identifiant INTERNE — jamais expose dans les DTO).
     *
     * @param  list<string>  $companyIds
     * @return array<string, array{rating_avg: float, rating_count: int}>
     */
    public function sellerRatings(array $companyIds): array
    {
        if ($companyIds === []) {
            return [];
        }

        $ratings = [];

        /** @var list<object{company_id: string, rating_avg: string|float|null, rating_count: int|string}> $rows */
        $rows = MarketplaceReview::query()
            ->whereIn('company_id', $companyIds)
            ->where('status', MarketplaceReviewStatus::Approved->value)
            ->selectRaw('company_id, AVG(rating) AS rating_avg, COUNT(*) AS rating_count')
            ->groupBy('company_id')
            ->get()
            ->all();

        foreach ($rows as $row) {
            $ratings[(string) $row->company_id] = [
                'rating_avg' => round((float) $row->rating_avg, 1),
                'rating_count' => (int) $row->rating_count,
            ];
        }

        return $ratings;
    }
}
