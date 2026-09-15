<?php

declare(strict_types=1);

namespace App\Http\Middleware\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #7395 — Accès PASSAGER aux ressources voyage (sans compte, sans jeton tenant).
 *
 * Le portail « Espace voyageur » est destiné au **client final** de l'agence :
 * il ne possède ni compte Leopardo, ni jeton boutique `X-Travel-Shop-Token`
 * (secret B2B réservé à l'embout de boutique du tenant). Résultat avant ce
 * middleware : `GET /travel/shop/bookings/{ref}` répondait 401
 * (UNAUTHENTICATED) à tout vrai passager — la fonctionnalité était
 * inaccessible à son public cible.
 *
 * Preuve d'accès du passager : le couple **référence de réservation + code de
 * validation** figurant sur son e-billet. La référence n'est pas un secret
 * (elle est imprimée et dicible), le code l'est (16 caractères, vérifié par
 * hash à temps constant dans le contrôleur). C'est ce couple qui authentifie,
 * pas un jeton d'infrastructure.
 *
 * Ce middleware ne fait que RÉSOUDRE LE TENANT à partir de la ressource
 * demandée, puis repose le contexte tenant habituel (`tenant_scope_required` +
 * `withinTenant`) : le scope global `BelongsToCompany` s'applique comme sur
 * n'importe quelle route tenant, donc aucune fuite cross-tenant.
 *
 * Choix de sécurité : une référence ou un numéro de billet inconnu renvoie un
 * **404 uniforme**, identique à celui d'un code invalide — pas d'oracle
 * permettant de distinguer « n'existe pas » de « mauvais code ».
 */
class EnsureTravelPassengerAccess
{
    public function __construct(private readonly TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        $company = $this->resolveCompany($request);

        if (! $company instanceof Company) {
            abort(404);
        }

        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    /**
     * Le tenant est déduit de la RESSOURCE visée : la référence de réservation,
     * seul identifiant stable et « URL-safe » du passager (les numéros de
     * billet contiennent un `#`, hostile en segment de chemin — cf. #7395).
     *
     * `withoutGlobalScopes()` est explicite et volontaire : hors contexte tenant
     * (c'est le cas ici, on est justement en train de le résoudre), le scope
     * `company` doit être contourné — la garde d'accès reste le code de
     * validation, vérifié dans le contrôleur.
     */
    private function resolveCompany(Request $request): ?Company
    {
        $reference = $request->route('reference');

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        /** @var TravelBooking|null $booking */
        $booking = TravelBooking::query()
            ->withoutGlobalScopes()
            ->where('reference', $reference)
            ->first();

        return $booking instanceof TravelBooking
            ? Company::query()->find($booking->company_id)
            : null;
    }
}
