<?php

declare(strict_types=1);

namespace App\Http\Middleware\Travel;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierApiKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * TRAVEL-807 (#6086) — Authentification des transporteurs (API entrante).
 *
 * Le transporteur présente sa clé API dans le header `X-Partner-Key` ; la
 * clé est hashée (SHA-256, jamais en clair au repos — pattern
 * AuthenticateZktecoDevice #4934) et rattachée à une compagnie + un
 * transporteur. Le middleware pose le contexte tenant (setTenant +
 * tenant_scope_required) pour toute la requête, expose le transporteur via
 * `$request->attributes->get('travel_partner_carrier')`, restaure le
 * search_path en finally (pattern #4787) et journalise les échecs.
 */
class TravelPartnerAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
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
            $token = (string) $request->header('X-Partner-Key', '');

            if ($token === '') {
                return $this->unauthorized('PARTNER_KEY_MISSING');
            }

            /** @var TravelCarrierApiKey|null $apiKey */
            // #9741 — la clé est le SECRET d'authentification : on ne peut pas
            // la chercher « dans le tenant » puisque le tenant est justement ce
            // qu'elle sert à résoudre. `TravelCarrierApiKey` porte
            // `BelongsToCompany`, donc le global scope exigeait un contexte
            // déjà posé et levait `TenantContextMissingException` → 401
            // PARTNER_AUTH_FAILED sur TOUTE synchronisation transporteur.
            // La lecture est donc explicitement non scopée, et le hash
            // SHA-256 (unique) reste le seul critère de sélection.
            $apiKey = TravelCarrierApiKey::query()
                ->withoutGlobalScopes()
                ->where('api_key_hash', hash('sha256', $token))
                ->first();

            if (! $apiKey instanceof TravelCarrierApiKey || ! $apiKey->enabled) {
                return $this->unauthorized('INVALID_PARTNER_KEY');
            }

            /** @var Company|null $company */
            $company = Company::query()->whereKey($apiKey->company_id)->first();

            if (! $company instanceof Company || $company->status !== 'active') {
                return $this->unauthorized('PARTNER_COMPANY_UNAVAILABLE');
            }

            /** @var TravelCarrier|null $carrier */
            // #9741 — même raison : le transporteur est résolu APRÈS la clé,
            // donc avant que le contexte tenant ne soit posé.
            $carrier = TravelCarrier::query()
                ->withoutGlobalScopes()
                ->whereKey($apiKey->carrier_id)
                ->first();

            if (! $carrier instanceof TravelCarrier) {
                return $this->unauthorized('PARTNER_CARRIER_NOT_FOUND');
            }

            // Contexte tenant pour toute la requête (comme TenantMiddleware).
            app(TenantManager::class)->setTenant($company);
            app()->instance('tenant_scope_required', true);
            app()->instance('current_company', $company);

            $apiKey->forceFill(['last_used_at' => now()])->save();

            $request->attributes->set('travel_partner_carrier', $carrier);
            $request->attributes->set('travel_partner_company', $company);

            $response = $next($request);

            app(TenantManager::class)->resetToPrevious();

            return $response;
        } catch (\Throwable $exception) {
            Log::channel('audit')->warning('travel_partner_auth.error', [
                'error' => $exception->getMessage(),
                'ip' => $request->ip(),
            ]);

            return $this->unauthorized('PARTNER_AUTH_FAILED');
        } finally {
            // #9741 — `tenant_scope_required` et `current_company` sont des
            // singletons du conteneur : les laisser posés après la requête
            // faisait fuir le marqueur « surface tenant » dans la requête
            // SUIVANTE du même process (worker, ou test suivant). Toute
            // lecture non scopée y levait alors `TenantContextMissingException`
            // — c'est ce qui faisait échouer le rejeu de synchronisation en
            // 401 alors que la clé était valide.
            if (app()->bound('tenant_scope_required')) {
                app()->forgetInstance('tenant_scope_required');
            }

            if (app()->bound('current_company')) {
                app()->forgetInstance('current_company');
            }

            try {
                DB::statement('SET search_path TO '.$previousPath);
            } catch (\Throwable) {
                // restauration best-effort
            }
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
