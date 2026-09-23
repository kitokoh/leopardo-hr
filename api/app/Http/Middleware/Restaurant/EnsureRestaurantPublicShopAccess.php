<?php

declare(strict_types=1);

namespace App\Http\Middleware\Restaurant;

use App\Core\Http\Security\CaptchaVerifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RESTO-805 (#6226) — Acces a la boutique publique RestaurantManager.
 *
 * Resout le tenant par jeton (`X-Restaurant-Shop-Token`, hash SHA-256 en
 * base) puis pose le contexte tenant (current_company + tenant_scope_required)
 * : le scope global BelongsToCompany s'applique → aucune fuite cross-tenant
 * (fail-closed 401/403). Hook anti-bot : si
 * `restaurantmanager.public_shop.captcha_secret` est configure, un jeton
 * CAPTCHA (`X-Captcha-Token`) non vide est exige. Pattern identique a
 * EnsurePublicShopAccess (TRAVEL-1001/#6114).
 */
class EnsureRestaurantPublicShopAccess
{
    public function __construct(
        private readonly TenantManager $tenants,
        private readonly CaptchaVerifier $captcha,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // #8054 — VRAIE vérification serveur (`siteverify`, fail-closed) via
        // le vérificateur partagé : avant, seul le caractère non-vide du
        // header était exigé et le secret n'était jamais utilisé.
        $captchaSecret = (string) config('restaurantmanager.public_shop.captcha_secret', '');

        if ($captchaSecret !== '') {
            $captchaToken = trim((string) $request->header('X-Captcha-Token', ''));
            $verifyUrl = (string) config('restaurantmanager.public_shop.captcha_verify_url', '');

            if (! $this->captcha->verify($captchaSecret, $captchaToken, $request->ip(), $verifyUrl !== '' ? $verifyUrl : null)) {
                abort(403, 'Validation anti-bot requise (X-Captcha-Token).');
            }
        }

        $token = (string) $request->header('X-Restaurant-Shop-Token', '');

        if ($token === '') {
            abort(401, 'Jeton boutique manquant (X-Restaurant-Shop-Token).');
        }

        /** @var RestaurantPublicShopToken|null $shopToken */
        $shopToken = RestaurantPublicShopToken::query()
            ->where('token_hash', RestaurantPublicShopToken::hash($token))
            ->where('active', true)
            ->first();

        if (! $shopToken instanceof RestaurantPublicShopToken) {
            abort(401, 'Jeton boutique invalide.');
        }

        $company = Company::query()->find($shopToken->company_id);

        if (! $company instanceof Company) {
            abort(401, 'Tenant introuvable pour ce jeton.');
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
