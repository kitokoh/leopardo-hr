<?php

declare(strict_types=1);

namespace App\Http\Middleware\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RESTO-805 (#6226) — Accès à la boutique publique RestaurantManager.
 *
 * Résout le tenant par jeton (`X-Restaurant-Shop-Token`, hash SHA-256 en
 * base — pattern EnsurePublicShopAccess #6114) puis pose le contexte tenant
 * (current_company + tenant_scope_required) : le scope global
 * BelongsToCompany s'applique → aucune fuite cross-tenant (fail-closed 401).
 * Hook anti-bot : si `restaurantmanager.public_shop.captcha_secret` est
 * configuré, un jeton CAPTCHA (`X-Captcha-Token`) non vide est exigé.
 */
class EnsureRestaurantShopPublicAccess
{
    public function __construct(private readonly TenantManager $tenants)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Hook anti-bot (CAPTCHA) : activé uniquement si un secret est configuré.
        $captchaSecret = (string) config('restaurantmanager.public_shop.captcha_secret', '');

        if ($captchaSecret !== '' && trim((string) $request->header('X-Captcha-Token', '')) === '') {
            abort(403, __('restaurant.public_shop.captcha_required'));
        }

        $token = (string) $request->header('X-Restaurant-Shop-Token', '');

        if ($token === '') {
            // Les webhooks entrants des marketplaces ne peuvent pas porter de
            // header custom : le jeton est alors passé en query param
            // (`?token=…`, RESTO-806/#6227) — l'URL est configurée côté
            // marketplace, le jeton n'apparaît jamais dans les logs du client.
            $token = (string) $request->query('token', '');
        }

        if ($token === '') {
            abort(401, __('restaurant.public_shop.token_missing'));
        }

        /** @var RestaurantPublicShopToken|null $shopToken */
        $shopToken = RestaurantPublicShopToken::query()
            ->where('token_hash', RestaurantPublicShopToken::hash($token))
            ->where('active', true)
            ->first();

        if (! $shopToken instanceof RestaurantPublicShopToken) {
            abort(401, __('restaurant.public_shop.token_invalid'));
        }

        $company = Company::query()->find($shopToken->company_id);

        if (! $company instanceof Company) {
            abort(401, __('restaurant.public_shop.tenant_not_found'));
        }

        $shopToken->forceFill(['last_used_at' => now()])->save();

        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }
}
