<?php

declare(strict_types=1);

namespace App\Http\Middleware\Retail;

use App\Modules\Retail\Application\Services\RetailBuyerAccountService;
use App\Modules\Retail\Domain\Models\MarketplaceBuyer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification des comptes acheteurs marketplace (BC-17 RETAIL, #7814).
 *
 * Routes `/api/v1/public/market/account/*` (hors register/login) : exige un
 * jeton Bearer opaque (`mkb_` + 64 hex) emis par RetailBuyerAccountService.
 * Comptes PLATEFORME (schema public) — AUCUN rapport avec Sanctum tenant.
 *
 * Fail-closed : jeton absent, malforme, inconnu ou expire → 401 uniforme
 * (`UNAUTHENTICATED`), sans distinction de cause (pas de probing). Le buyer
 * resolu est pose dans le conteneur sous `market_buyer` pour les
 * controleurs.
 */
class EnsureMarketBuyerAuth
{
    public function __construct(private readonly RetailBuyerAccountService $accounts) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $buyer = $this->accounts->buyerForBearerToken($request->bearerToken());

        if (! $buyer instanceof MarketplaceBuyer) {
            abort(401, 'UNAUTHENTICATED');
        }

        app()->instance('market_buyer', $buyer);

        try {
            return $next($request);
        } finally {
            app()->forgetInstance('market_buyer');
        }
    }
}
