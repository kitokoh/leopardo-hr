<?php

declare(strict_types=1);

namespace App\Http\Middleware\Restaurant;

use App\Core\Http\Security\CaptchaVerifier;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
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
 *
 * BOS-050 (#8208, tranche 7) : la bascule tenant est deléguée au socle
 * mutualisé {@see PublicTenantResolver} (`withinTenant()` — marqueur
 * `tenant_scope_required` restauré en `finally`, imbrication sûre) et la
 * garde société à {@see PublicTenantResolver::assertAccessible()} —
 * durcissement aligné sur l'invariant partagé : une société
 * `suspended`/`expired` n'est plus servie (401 sur ce chemin jeton) ;
 * cette surface ne vérifiait historiquement AUCUN statut. La résolution
 * bornée (jeton boutique SHA-256) reste portée ici, comme prévu par le
 * socle. La feature verticale n'est volontairement PAS ajoutée : jamais
 * vérifiée sur cette surface (périmètre minimal).
 */
class EnsureRestaurantPublicShopAccess
{
    public function __construct(
        private readonly CaptchaVerifier $captcha,
        private readonly PublicTenantResolver $publicTenants,
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

        // BOS-050 — garde société mutualisée : suspended/expired → 401
        // (chemin jeton). Feature verticale non vérifiée ici (historique).
        $this->publicTenants->assertAccessible($company, null, null, 401);

        $shopToken->forceFill(['last_used_at' => now()])->save();

        return $this->publicTenants->withinTenant($company, fn (): Response => $next($request));
    }
}
