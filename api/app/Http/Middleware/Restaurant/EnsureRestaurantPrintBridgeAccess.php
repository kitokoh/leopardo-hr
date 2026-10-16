<?php

declare(strict_types=1);

namespace App\Http\Middleware\Restaurant;

use App\Core\Tenant\Domain\Models\Company;
use App\Shared\Services\PublicCommerce\PublicTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST-OS-3 (Mission Leopardo Restaurant OS) — Accès du bridge d'impression
 * local (agent installé au poste cuisine).
 *
 * Le bridge est un composant LOCAL (Raspberry Pi / mini-PC / service système)
 * qui récupère les travaux d'impression et les remet à l'imprimante thermique
 * (USB / réseau 9100 / CUPS). Il n'a pas de compte employé : sa confiance est
 * portée par un JETON PARTAGÉ, comparé en temps constant sur un HASH
 * (`RESTAURANT_PRINT_BRIDGE_TOKEN_HASH`) — le secret en clair n'est jamais
 * stocké côté API.
 *
 * Fail-closed :
 *  - bridge non activé (`RESTAURANT_PRINT_BRIDGE_ENABLED` ≠ true) → 403 ;
 *  - jeton non configuré (hash vide) → 403 ;
 *  - jeton absent/invalide → 401 ;
 *  - société inconnue / suspendue / sans feature → 401.
 *
 * Le tenant est posé via {@see PublicTenantResolver::withinTenant()} (marqueur
 * `tenant_scope_required` + contexte) : le scope BelongsToCompany s'applique et
 * aucune donnée d'un autre tenant n'est atteignable.
 */
final class EnsureRestaurantPrintBridgeAccess
{
    public function __construct(private readonly PublicTenantResolver $publicTenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('restaurantmanager.printing.bridge_enabled', false)) {
            abort(403, 'print_bridge_disabled');
        }

        $configuredHash = (string) config('restaurantmanager.printing.bridge_token_hash', '');

        if ($configuredHash === '') {
            abort(403, 'print_bridge_not_configured');
        }

        $presented = $this->presentedToken($request);

        if ($presented === '' || ! hash_equals($configuredHash, hash('sha256', $presented))) {
            abort(401, 'print_bridge_token_invalid');
        }

        $companyId = trim((string) $request->header('X-Company-Id', ''));

        if ($companyId === '') {
            abort(401, 'print_bridge_company_missing');
        }

        /** @var Company|null $company */
        $company = Company::query()->find($companyId);

        if (! $company instanceof Company) {
            abort(401, 'print_bridge_company_unknown');
        }

        // Statut société + feature verticale (fail-closed, échec uniforme).
        $this->publicTenants->assertAccessible($company, 'restaurantmanager', null, 401);

        return $this->publicTenants->withinTenant($company, fn (): Response => $next($request));
    }

    private function presentedToken(Request $request): string
    {
        $bearer = trim((string) $request->bearerToken());

        if ($bearer !== '') {
            return $bearer;
        }

        return trim((string) $request->header('X-Restaurant-Print-Bridge-Token', ''));
    }
}
