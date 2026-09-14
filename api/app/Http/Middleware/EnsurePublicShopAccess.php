<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelPublicShopToken;
use App\Modules\TravelAgency\Domain\Models\TravelTicket;
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
 *
 * #7395 — ACCÈS PASSAGER : un voyageur ne possède pas le jeton boutique du
 * tenant (il n'est délivré qu'à l'agence). Pour les routes bornées à UNE
 * ressource (`{reference}` = réservation, `{ticket}` = billet), le tenant est
 * donc résolu par la ressource elle-même — le contrôle d'accès réel reste le
 * CODE DE VALIDATION du billet (secret partagé), vérifié par le contrôleur
 * AVANT toute donnée (404/403 sinon). Les routes non bornées (recherche,
 * réservation, paiement) exigent toujours le jeton boutique.
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

        if ($token !== '') {
            $company = $this->companyFromShopToken($token);
        } else {
            $company = $this->companyFromPassengerResource($request);

            if (! $company instanceof Company) {
                abort(401, 'Jeton boutique manquant (X-Travel-Shop-Token).');
            }
        }

        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    /**
     * Tenant du jeton boutique (fail-closed 401).
     */
    private function companyFromShopToken(string $token): Company
    {
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

        return $company;
    }

    /**
     * Tenant déduit de la ressource visée (réservation ou billet) pour un
     * passager sans jeton boutique (#7395).
     *
     * La résolution ne s'applique QU'aux routes portant `{reference}` ou
     * `{ticket}` : ailleurs, on retourne `null` → 401 (recherche, réservation
     * et paiement restent protégés par le jeton tenant). Une référence
     * ambiguë (même référence chez deux tenants) ou inconnue est un 404 :
     * fail-closed, jamais de sélection arbitraire de tenant.
     */
    private function companyFromPassengerResource(Request $request): ?Company
    {
        $reference = $request->route('reference');

        if (is_string($reference) && $reference !== '') {
            $matches = TravelBooking::query()
                ->withoutGlobalScope('company')
                ->where('reference', $reference)
                ->limit(2)
                ->get();

            /** @var TravelBooking|null $booking */
            $booking = $matches->count() === 1 ? $matches->first() : null;

            if (! $booking instanceof TravelBooking) {
                abort(404);
            }

            return Company::query()->find($booking->company_id);
        }

        $ticket = $request->route('ticket');

        // Selon l'ordre des middlewares, le binding implicite a déjà pu
        // résoudre le billet (SubstituteBindings fait partie du groupe `api`,
        // exécuté AVANT les middlewares de route) : on accepte les deux formes.
        if ($ticket instanceof TravelTicket) {
            return Company::query()->find($ticket->company_id);
        }

        if (is_string($ticket) && ctype_digit($ticket)) {
            /** @var TravelTicket|null $model */
            $model = TravelTicket::query()
                ->withoutGlobalScope('company')
                ->find((int) $ticket);

            if (! $model instanceof TravelTicket) {
                abort(404);
            }

            return Company::query()->find($model->company_id);
        }

        return null;
    }
}
