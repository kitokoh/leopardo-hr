<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Enums\RetailProductStatus;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\MarketCustomerFavorite;
use App\Modules\Retail\Domain\Models\RetailProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue #7814 — Favoris des acheteurs de Leopardo Marché (produits et
 * boutiques).
 *
 * Surface CONNECTÉE (guard dédié `market_customer`), strictement bornée au
 * compte : chaque requête filtre par `customer_account_id`. Les cibles sont
 * référencées PAR VALEUR (product_id global / seller_slug public) ; la
 * résolution publique repasse par RetailMarketplaceService — fail-closed :
 * l'ajout exige une cible PUBLIQUEMENT visible, la lecture n'expose que les
 * cibles encore publiques (un produit dépublié reste en favori mais est
 * signalé `available: false` sans donnée interne).
 */
class MarketCustomerFavoriteController extends Controller
{
    public function __construct(private readonly RetailMarketplaceService $marketplace) {}

    /**
     * GET /public/market/account/favorites — favoris du compte, cibles
     * résolues publiquement (fail-closed).
     */
    public function index(Request $request): JsonResponse
    {
        $account = $this->authenticated($request);

        $favorites = MarketCustomerFavorite::query()
            ->where('customer_account_id', $account->id)
            ->orderByDesc('id')
            ->get();

        $eligible = $this->marketplace->eligibleCompanyIds();

        /** @var list<int> $productIds */
        $productIds = $favorites
            ->where('target_type', MarketCustomerFavorite::TYPE_PRODUCT)
            ->pluck('product_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();

        $products = [];
        $available = [];

        if ($productIds !== [] && $eligible !== []) {
            $found = RetailProduct::query()
                ->withoutGlobalScope('company')
                ->whereIn('company_id', $eligible)
                ->whereIn('id', $productIds)
                ->where('status', RetailProductStatus::Published->value)
                ->where('online_visible', true)
                ->get();

            $companyIds = $found->pluck('company_id')->map(static fn ($id): string => (string) $id)->unique()->values()->all();
            $settings = $this->marketplace->settingsByCompanyId($companyIds);
            $companies = $this->marketplace->companiesById($companyIds);
            $available = $this->marketplace->availableProductIds($eligible, $productIds);

            foreach ($found as $product) {
                $companyId = (string) $product->company_id;
                $company = $companies[$companyId] ?? null;
                $products[(int) $product->id] = [
                    'id' => (int) $product->id,
                    'name' => $product->name,
                    'price_minor' => (int) $product->price_minor,
                    'currency' => $product->currency,
                    'image_url' => $product->image_url,
                    'available' => in_array((int) $product->id, $available, true),
                    'seller' => [
                        'name' => ($settings[$companyId] ?? null)?->shop_name,
                        'slug' => $company !== null ? (string) $company->slug : null,
                    ],
                ];
            }
        }

        $sellerSettings = $this->marketplace->settingsByCompanyId($eligible);
        $sellerCompanies = $this->marketplace->companiesById($eligible);
        $sellersBySlug = [];

        foreach ($sellerCompanies as $companyId => $company) {
            $slug = (string) $company->slug;
            $sellersBySlug[$slug] = [
                'name' => ($sellerSettings[$companyId] ?? null)?->shop_name,
                'slug' => $slug,
                'city' => ($sellerSettings[$companyId] ?? null)?->city,
            ];
        }

        return response()->json([
            'data' => $favorites
                ->map(static function (MarketCustomerFavorite $favorite) use ($products, $sellersBySlug): array {
                    return [
                        'id' => (int) $favorite->id,
                        'target_type' => $favorite->target_type,
                        'product' => $favorite->product_id !== null
                            ? ($products[(int) $favorite->product_id] ?? null)
                            : null,
                        'seller' => $favorite->seller_slug !== null
                            ? ($sellersBySlug[$favorite->seller_slug] ?? null)
                            : null,
                        'created_at' => $favorite->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    /**
     * POST /public/market/account/favorites — ajout idempotent (re-poster la
     * même cible renvoie le favori existant, 200).
     */
    public function store(Request $request): JsonResponse
    {
        $account = $this->authenticated($request);

        $data = $request->validate([
            'target_type' => ['required', 'in:product,seller'],
            'product_id' => ['required_if:target_type,product', 'nullable', 'integer', 'min:1'],
            'seller' => ['required_if:target_type,seller', 'nullable', 'string', 'max:120'],
        ]);

        $eligible = $this->marketplace->eligibleCompanyIds();
        $targetType = (string) $data['target_type'];
        $productId = null;
        $sellerSlug = null;

        if ($targetType === MarketCustomerFavorite::TYPE_PRODUCT) {
            $productId = (int) $data['product_id'];
            $product = $this->marketplace->findEligibleProduct($eligible, $productId);

            if (! $product instanceof RetailProduct) {
                // Produit inconnu, non publié ou vendeur non opt-in :
                // fail-closed uniforme (pas de probing).
                abort(404);
            }
        } else {
            $sellerSlug = (string) $data['seller'];
            $company = $this->marketplace->eligibleCompanyForSlug($eligible, $sellerSlug);

            if (! $company instanceof Company) {
                abort(404);
            }
        }

        $existing = MarketCustomerFavorite::query()
            ->where('customer_account_id', $account->id)
            ->where('target_type', $targetType)
            ->when(
                $targetType === MarketCustomerFavorite::TYPE_PRODUCT,
                static fn ($query) => $query->where('product_id', $productId),
                static fn ($query) => $query->where('seller_slug', $sellerSlug),
            )
            ->first();

        if ($existing instanceof MarketCustomerFavorite) {
            return response()->json(['data' => ['id' => (int) $existing->id, 'created' => false]]);
        }

        $favorite = MarketCustomerFavorite::query()->create([
            'customer_account_id' => $account->id,
            'target_type' => $targetType,
            'product_id' => $productId,
            'seller_slug' => $sellerSlug,
        ]);

        return response()->json(['data' => ['id' => (int) $favorite->id, 'created' => true]], 201);
    }

    /**
     * DELETE /public/market/account/favorites/{favorite} — retrait, borné au
     * compte (404 fail-closed sur le favori d'un autre acheteur).
     */
    public function destroy(Request $request, string $favorite): JsonResponse
    {
        $account = $this->authenticated($request);

        $found = ctype_digit($favorite)
            ? MarketCustomerFavorite::query()
                ->where('customer_account_id', $account->id)
                ->find((int) $favorite)
            : null;

        if (! $found instanceof MarketCustomerFavorite) {
            abort(404);
        }

        $found->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function authenticated(Request $request): MarketCustomerAccount
    {
        $account = $request->user('market_customer');

        abort_unless($account instanceof MarketCustomerAccount, 401);

        return $account;
    }
}
