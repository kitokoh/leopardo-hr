<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Retail\EnsureMarketBuyerAuth;
use App\Modules\Retail\Application\Services\RetailBuyerAccountService;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Modules\Retail\Interfaces\Api\V1\Requests\LoginMarketBuyerRequest;
use App\Modules\Retail\Interfaces\Api\V1\Requests\RegisterMarketBuyerRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Comptes acheteurs PUBLICS de la marketplace Leopardo Marche
 * (BC-17 RETAIL, #7814).
 *
 * Routes `/api/v1/public/market/account/*` (`throttle:shop-public` +
 * throttle strict dedie sur register/login) :
 *   POST /account/register         → inscription legere (201 + jeton)
 *   POST /account/login            → connexion (200 + jeton, 401 uniforme)
 *   POST /account/session/restore  → pose le cookie HttpOnly pour un jeton
 *                                    legacy valide (migration douce #8096)
 *   POST /account/logout           → revocation du jeton courant (auth buyer)
 *   GET  /account/me               → profil du buyer authentifie
 *   GET  /account/orders           → historique cross-tenant des commandes liees
 *
 * #8096 — la session est desormais portee par un cookie
 * `HttpOnly; Secure; SameSite=None` (front cross-origin) pose a
 * register/login/restore, borne au chemin `/api/v1/public/market`. Le jeton
 * reste retourne dans le corps JSON pendant la migration douce (anciens
 * clients), mais le front marketplace ne le persiste plus (fin du
 * compromis localStorage #7979).
 *
 * Comptes PLATEFORME (tables centrales, jamais de company_id expose) —
 * DTO publics stricts : reference, vendeur public, totaux, statut
 * logistique, jeton de suivi. AUCUNE donnee interne (spec §3).
 */
class RetailMarketAccountController extends Controller
{
    public function __construct(
        private readonly RetailBuyerAccountService $accounts,
        private readonly RetailMarketplaceService $marketplace,
    ) {}

    /**
     * POST /public/market/account/register — inscription legere.
     */
    public function register(RegisterMarketBuyerRequest $request): JsonResponse
    {
        $result = $this->accounts->register(
            name: (string) $request->input('name'),
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
            phone: $request->filled('phone') ? (string) $request->input('phone') : null,
        );

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'buyer' => $this->buyerPayload($result['buyer']),
            ],
        ], 201)->withCookie($this->sessionCookie($result['token']));
    }

    /**
     * POST /public/market/account/login — 401 uniforme si identifiants
     * invalides (jamais de distinction email/mot de passe).
     */
    public function login(LoginMarketBuyerRequest $request): JsonResponse
    {
        $result = $this->accounts->login(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
        );

        if ($result === null) {
            abort(401, 'INVALID_CREDENTIALS');
        }

        return response()->json([
            'data' => [
                'token' => $result['token'],
                'buyer' => $this->buyerPayload($result['buyer']),
            ],
        ])->withCookie($this->sessionCookie($result['token']));
    }

    /**
     * POST /public/market/account/session/restore — restauration/refresh de
     * session (#8096) : pour un jeton legacy valide (Bearer localStorage),
     * pose le cookie HttpOnly qui prend le relais ; idempotent quand la
     * requete est deja authentifiee par cookie. Le middleware `market.buyer`
     * a deja valide le jeton (401 uniforme sinon).
     */
    public function restoreSession(Request $request): JsonResponse
    {
        $token = EnsureMarketBuyerAuth::requestToken($request);

        if (! is_string($token) || $token === '') {
            abort(401, 'UNAUTHENTICATED');
        }

        return response()->json([
            'data' => [
                'restored' => true,
                'buyer' => $this->buyerPayload($this->currentBuyer()),
            ],
        ])->withCookie($this->sessionCookie($token));
    }

    /**
     * POST /public/market/account/logout — revoque le jeton courant
     * (idempotent) et expire le cookie HttpOnly (#8096), quelle que soit la
     * source du jeton (Bearer ou cookie).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->accounts->revokeBearerToken(EnsureMarketBuyerAuth::requestToken($request));

        return response()->json(['data' => ['logged_out' => true]])
            ->withCookie(Cookie::forget(
                RetailBuyerAccountService::SESSION_COOKIE,
                RetailBuyerAccountService::SESSION_COOKIE_PATH,
            ));
    }

    /**
     * GET /public/market/account/me — profil du buyer authentifie.
     */
    public function me(): JsonResponse
    {
        return response()->json(['data' => $this->buyerPayload($this->currentBuyer())]);
    }

    /**
     * GET /public/market/account/orders — historique cross-tenant des
     * commandes en ligne liees au compte (les commandes invitees non liees
     * restent accessibles par reference + jeton de suivi uniquement).
     */
    public function orders(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $filters */
        $filters = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $buyer = $this->currentBuyer();

        $orders = RetailOrder::query()
            ->withoutGlobalScope('company')
            ->where('buyer_id', (int) $buyer->id)
            ->where('source', 'online')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(isset($filters['per_page']) ? (int) $filters['per_page'] : 20);

        /** @var list<RetailOrder> $items */
        $items = $orders->items();

        $companyIds = array_values(array_unique(array_map(
            static fn (RetailOrder $order): string => (string) $order->company_id,
            $items,
        )));

        $settings = $this->marketplace->settingsByCompanyId($companyIds);
        $companies = $this->marketplace->companiesById($companyIds);
        $orderItems = $this->itemsByOrder($items);

        return response()->json([
            'data' => array_map(
                function (RetailOrder $order) use ($settings, $companies, $orderItems): array {
                    $companyId = (string) $order->company_id;
                    $company = $companies[$companyId] ?? null;
                    $sellerSettings = $settings[$companyId] ?? null;

                    return [
                        'reference' => $order->reference,
                        'seller' => [
                            'name' => $sellerSettings?->shop_name,
                            'slug' => $company !== null ? (string) $company->slug : null,
                            'city' => $sellerSettings?->city,
                        ],
                        'total_minor' => (int) $order->total_minor,
                        'currency' => $order->currency,
                        'fulfillment_status' => $order->fulfillment_status?->value,
                        'created_at' => $order->created_at?->toIso8601String(),
                        'tracking_token' => $order->tracking_token,
                        'items' => $orderItems[(int) $order->id] ?? [],
                    ];
                },
                $items,
            ),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ]);
    }

    /**
     * Lignes publiques des commandes affichees (nom produit, quantite,
     * product_id public — necessaire au formulaire d'avis post-livraison).
     *
     * @param  list<RetailOrder>  $orders
     * @return array<int, list<array<string, mixed>>>
     */
    private function itemsByOrder(array $orders): array
    {
        if ($orders === []) {
            return [];
        }

        $byOrder = [];

        $rows = RetailOrderItem::query()
            ->withoutGlobalScope('company')
            ->whereIn('order_id', array_map(static fn (RetailOrder $order): int => (int) $order->id, $orders))
            ->whereIn('company_id', array_map(static fn (RetailOrder $order): string => (string) $order->company_id, $orders))
            ->orderBy('line_index')
            ->get();

        foreach ($rows as $row) {
            $byOrder[(int) $row->order_id][] = [
                'product_id' => (int) $row->product_id,
                'product_name' => $row->product_name,
                'quantity' => (float) $row->quantity,
                'unit_price_minor' => (int) $row->unit_price_minor,
                'line_total_minor' => (int) $row->line_total_minor,
            ];
        }

        return $byOrder;
    }

    /**
     * Buyer authentifie pose par le middleware `market.buyer`.
     */
    private function currentBuyer(): MarketplaceBuyer
    {
        $buyer = app('market_buyer');

        if (! $buyer instanceof MarketplaceBuyer) {
            abort(401, 'UNAUTHENTICATED');
        }

        return $buyer;
    }

    /**
     * Cookie de session acheteur (#8096) : `HttpOnly` (illisible par le JS
     * de la page — fin de l'exfiltration XSS par localStorage), `Secure`
     * (exige par `SameSite=None` ; accepte par les navigateurs sur
     * localhost en dev), `SameSite=None` car le front marketplace est
     * servi cross-origin (projet Vercel dedie), chemin borne a la surface
     * publique marche, duree = TTL du jeton.
     */
    private function sessionCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            RetailBuyerAccountService::SESSION_COOKIE,
            $token,
            RetailBuyerAccountService::SESSION_COOKIE_MINUTES,
            RetailBuyerAccountService::SESSION_COOKIE_PATH,
            null,
            true,
            true,
            false,
            'None',
        );
    }

    /**
     * DTO public du compte — jamais de mot de passe ni de donnee interne.
     *
     * @return array<string, mixed>
     */
    private function buyerPayload(MarketplaceBuyer $buyer): array
    {
        return [
            'name' => $buyer->name,
            'email' => $buyer->email,
            'phone' => $buyer->phone,
            'created_at' => $buyer->created_at?->toIso8601String(),
        ];
    }
}
