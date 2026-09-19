<?php

declare(strict_types=1);

namespace App\Http\Middleware\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TRAVEL-DISTRIBUTION (#7641) — Authentification des distributeurs (lecture).
 *
 * Le distributeur présente sa clé dans le header `X-Distributor-Key` ; la
 * clé est hashée (SHA-256, jamais en clair au repos) et rattachée à une
 * compagnie + une liste de scopes de LECTURE. Miroir exact du middleware
 * transporteurs `TravelPartnerAuthMiddleware` (#6086) : contexte tenant posé
 * pour toute la requête, search_path restauré en finally (#4787), échecs
 * journalisés. Le scope requis est passé en paramètre de middleware
 * (`travel.distributor:catalog.read`) — scope manquant → 403 fail-closed.
 * Stats d'usage : `last_used_at` + `usage_count` incrémenté par requête.
 */
class TravelDistributorAuthMiddleware
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $previousPath = 'public,shared_tenants';
        try {
            $row = DB::selectOne('SHOW search_path');
            if (is_object($row) && property_exists($row, 'search_path')) {
                $previousPath = (string) $row->search_path;
            }
        } catch (\Throwable) {
            // défaut conservé
        }
        DB::statement('SET search_path TO shared_tenants,public');

        try {
            $token = (string) $request->header('X-Distributor-Key', '');

            if ($token === '') {
                return $this->unauthorized('DISTRIBUTOR_KEY_MISSING');
            }

            /** @var TravelDistributorKey|null $apiKey */
            $apiKey = TravelDistributorKey::query()
                ->where('api_key_hash', hash('sha256', $token))
                ->first();

            if (! $apiKey instanceof TravelDistributorKey || ! $apiKey->enabled) {
                return $this->unauthorized('INVALID_DISTRIBUTOR_KEY');
            }

            if (! $apiKey->hasScope($scope)) {
                return new JsonResponse([
                    'error' => 'DISTRIBUTOR_SCOPE_MISSING',
                    'message' => 'DISTRIBUTOR_SCOPE_MISSING',
                ], 403);
            }

            /** @var Company|null $company */
            $company = Company::query()->whereKey($apiKey->company_id)->first();

            if (! $company instanceof Company || $company->status !== 'active') {
                return $this->unauthorized('DISTRIBUTOR_COMPANY_UNAVAILABLE');
            }

            // Stats d'usage par distributeur (critère #7641).
            $apiKey->forceFill(['last_used_at' => now()])->save();
            TravelDistributorKey::query()->whereKey($apiKey->id)->increment('usage_count');

            $request->attributes->set('travel_distributor_key', $apiKey);
            $request->attributes->set('travel_distributor_company', $company);
        } catch (\Throwable $exception) {
            Log::channel('audit')->warning('travel_distributor_auth.error', [
                'error' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            return $this->unauthorized('DISTRIBUTOR_AUTH_FAILED');
        } finally {
            try {
                DB::statement('SET search_path TO '.$previousPath);
            } catch (\Throwable) {
                // restauration best-effort
            }
        }

        // Contexte tenant pour toute la requête, relâché en finally (pattern
        // canonique TenantMiddleware/EnsurePublicShopAccess) : le marqueur
        // `tenant_scope_required` ne fuit plus hors de la requête, et les
        // exceptions du contrôleur (404 ModelNotFound d'une ressource d'un
        // autre tenant…) remontent au handler HTTP au lieu d'être converties
        // en 401 générique par le catch d'authentification ci-dessus.
        app()->instance('tenant_scope_required', true);

        try {
            return app(TenantManager::class)->withinTenant($company, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }

    private function unauthorized(string $error): JsonResponse
    {
        return new JsonResponse([
            'error' => $error,
            'message' => $error,
        ], 401);
    }
}
