<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TRAVEL-1001 (#6114) — Accès à la boutique publique.
 *
 * Résout le tenant par jeton (`X-Travel-Shop-Token`, hash SHA-256 en base)
 * puis pose le contexte tenant (current_company + tenant_scope_required) :
 * le scope global BelongsToCompany s'applique → aucune fuite cross-tenant
 * (fail-closed 401/403). Hook anti-bot : si `travel.public_shop.captcha_secret`
 * est configuré, un jeton CAPTCHA (`X-Captcha-Token`) non vide est exigé.
 */
class EnsurePublicShopAccess
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Hook anti-bot (CAPTCHA) : activé uniquement si un secret est configuré.
        $captchaSecret = (string) config('travel.public_shop.captcha_secret', '');

        if ($captchaSecret !== '' && trim((string) $request->header('X-Captcha-Token', '')) === '') {
            abort(403, 'Validation anti-bot requise (X-Captcha-Token).');
        }

        $token = (string) $request->header('X-Travel-Shop-Token', '');

        if ($token === '') {
            abort(401, 'Jeton boutique manquant (X-Travel-Shop-Token).');
        }

        /** @var TravelPublicShopToken|null $shopToken */
        $shopToken = TravelPublicShopToken::query()
            ->where('token_hash', TravelPublicShopToken::hash($token))
            ->where('active', true)
            ->first();

        if (! $shopToken instanceof TravelPublicShopToken) {
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
