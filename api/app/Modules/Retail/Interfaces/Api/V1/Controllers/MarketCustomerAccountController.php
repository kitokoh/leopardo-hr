<?php

declare(strict_types=1);

namespace App\Modules\Retail\Interfaces\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Retail\Application\Services\MarketCustomerAccountService;
use App\Modules\Retail\Application\Services\RetailMarketplaceService;
use App\Modules\Retail\Domain\Models\MarketCustomerAccount;
use App\Modules\Retail\Domain\Models\RetailOrder;
use App\Modules\Retail\Domain\Models\RetailOrderItem;
use App\Shared\Rules\NotCommonPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Issue #7814 — Comptes acheteurs GRAND PUBLIC de Leopardo Marché
 * (BC-17 RETAIL, backlog post-v1 spec MARKETPLACE_RETAIL_PUBLIC.md §6).
 *
 * Auth par tokens Sanctum sur le guard DÉDIÉ `market_customer` (provider
 * plateforme `market_customer_accounts`, schéma public) : jamais le guard
 * employés ni super-admin. Login/register sous `throttle:auth-sensitive`
 * (e-mail + IP) + verrouillage 15 min après 5 échecs (pattern
 * TravelCustomerAccountController #7739 / PlatformAuthController #6563).
 *
 * Isolation : « mes commandes » est borné par `customer_account_id` — un
 * acheteur ne voit que SES commandes, et seule la surface publique
 * marketplace est exposée (nom public de boutique, jamais d'identifiant
 * tenant ni de donnée interne de vendeur).
 */
class MarketCustomerAccountController extends Controller
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    public function __construct(
        private readonly MarketCustomerAccountService $accounts,
        private readonly RetailMarketplaceService $marketplace,
    ) {}

    /**
     * Inscription : hash du mot de passe (jamais en clair) + rattachement
     * des commandes en ligne existantes portant le même e-mail client
     * (« à la création » du compte, critère #7814).
     */
    public function register(Request $request): JsonResponse
    {
        // Normalisation avant validation : l'unicité e-mail est insensible à
        // la casse (le compte est toujours stocké en minuscules).
        $rawEmail = $request->input('email');
        if (is_string($rawEmail)) {
            $request->merge(['email' => mb_strtolower($rawEmail)]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255', 'unique:market_customer_accounts,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => ['required', 'string', 'max:255', Password::min(12)->numbers(), new NotCommonPassword],
        ]);

        $account = MarketCustomerAccount::query()->create([
            'name' => $data['name'],
            'email' => mb_strtolower((string) $data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make((string) $data['password']),
            'last_login_at' => now(),
        ]);

        $claimed = $this->accounts->claimOrdersByEmail($account);

        return response()->json([
            'data' => [
                'account' => $this->accountPayload($account),
                'token' => $account->createToken('market-web')->plainTextToken,
                'claimed_orders' => $claimed,
            ],
        ], 201);
    }

    /**
     * Connexion : throttle route `auth-sensitive` + verrouillage applicatif
     * (5 échecs / 15 min par e-mail + IP) — jamais de distinction « compte
     * inconnu / mot de passe faux ».
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $accountKey = 'market_customer_login_'.mb_strtolower((string) $data['email']);
        $attemptKey = $accountKey.':'.$request->ip();
        $lockKey = $accountKey.':lock';

        if (Cache::get($lockKey)) {
            return response()->json([
                'message' => __('auth.account_locked'),
            ], 423);
        }

        /** @var MarketCustomerAccount|null $account */
        $account = MarketCustomerAccount::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $data['email'])])
            ->first();

        if (! $account instanceof MarketCustomerAccount || ! Hash::check((string) $data['password'], $account->password)) {
            $attempts = (int) Cache::get($attemptKey, 0) + 1;
            Cache::put($attemptKey, $attempts, now()->addMinutes(15));

            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {
                Cache::put($lockKey, true, now()->addMinutes(15));
            }

            return response()->json(['message' => __('auth.failed')], 401);
        }

        Cache::forget($attemptKey);
        Cache::forget($lockKey);

        $account->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'data' => [
                'account' => $this->accountPayload($account),
                'token' => $account->createToken('market-web')->plainTextToken,
            ],
        ]);
    }

    /**
     * Déconnexion : révocation du SEUL token courant (les autres appareils
     * restent connectés).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $this->authenticated($request)->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['data' => ['logged_out' => true]]);
    }

    /**
     * Profil de l'acheteur connecté.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => ['account' => $this->accountPayload($this->authenticated($request))]]);
    }

    /**
     * « Mes commandes » cross-boutiques, strictement bornées au compte.
     */
    public function orders(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $orders = $this->accounts->ordersFor(
            $this->authenticated($request),
            isset($filters['per_page']) ? (int) $filters['per_page'] : 20,
        );

        /** @var list<RetailOrder> $items */
        $items = $orders->items();

        $companyIds = array_values(array_unique(array_map(
            static fn (RetailOrder $order): string => (string) $order->company_id,
            $items,
        )));

        $settings = $this->marketplace->settingsByCompanyId($companyIds);
        $companies = $this->marketplace->companiesById($companyIds);

        return response()->json([
            'data' => array_map(
                fn (RetailOrder $order): array => $this->orderPayload(
                    $order,
                    $settings[(string) $order->company_id] ?? null,
                    $companies[(string) $order->company_id] ?? null,
                ),
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
     * Acheteur authentifié via le guard dédié — jamais un employé ni un
     * super-admin (les tokens des autres guards ne résolvent pas ce modèle).
     */
    private function authenticated(Request $request): MarketCustomerAccount
    {
        $account = $request->user('market_customer');

        abort_unless($account instanceof MarketCustomerAccount, 401);

        return $account;
    }

    /**
     * @return array{id: int, name: string, email: string, phone: string|null, created_at: string|null}
     */
    private function accountPayload(MarketCustomerAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'email' => $account->email,
            'phone' => $account->phone,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }

    /**
     * Charge utile publique d'une commande : référence, statuts, totaux,
     * lignes, jeton de suivi (le compte est propriétaire) et NOM PUBLIC de
     * la boutique. Aucun identifiant tenant.
     *
     * @param  \App\Modules\Retail\Domain\Models\RetailOnlineSettings|null  $settings
     * @param  \App\Core\Tenant\Domain\Models\Company|null  $company
     * @return array<string, mixed>
     */
    private function orderPayload(RetailOrder $order, $settings, $company): array
    {
        $items = RetailOrderItem::query()
            ->withoutGlobalScope('company')
            ->where('company_id', (string) $order->company_id)
            ->where('order_id', (int) $order->id)
            ->orderBy('line_index')
            ->get()
            ->map(static fn (RetailOrderItem $item): array => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product_name,
                'quantity' => $item->quantity,
                'unit_price_minor' => (int) $item->unit_price_minor,
                'line_total_minor' => (int) $item->line_total_minor,
            ])
            ->values()
            ->all();

        return [
            'reference' => $order->reference,
            'fulfillment_status' => $order->fulfillment_status?->value,
            'total_minor' => (int) $order->total_minor,
            'currency' => $order->currency,
            'tracking_token' => $order->tracking_token,
            'created_at' => $order->created_at?->toIso8601String(),
            'items' => $items,
            'seller' => [
                'name' => $settings?->shop_name,
                'slug' => $company !== null ? (string) $company->slug : null,
                'city' => $settings?->city,
            ],
        ];
    }
}
