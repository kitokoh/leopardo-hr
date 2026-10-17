<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Interfaces\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BC-38 PAIEMENTS (#8689) — authentification service-a-service.
 *
 * L'API interne n'est pas consommee par un utilisateur : elle l'est par un
 * autre BC (Paie, Cagnottes, VTC, Boutique). Une session utilisateur n'a donc
 * aucun sens ici — le seul mode d'authentification est un jeton de service.
 *
 * **Fail-closed** : sans jeton configure cote serveur, TOUTE requete est
 * rejetee. Un environnement mal configure ne doit jamais ouvrir l'API.
 */
class EnsurePaymentServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('paiements.service_token');

        if (! is_string($expected) || $expected === '') {
            return response()->json([
                'code' => 'service_auth_not_configured',
                'message' => 'Authentification service non configuree.',
                'data' => null,
            ], 503);
        }

        $provided = $request->bearerToken() ?? (string) $request->header('X-Service-Token', '');

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'code' => 'unauthenticated_service',
                'message' => 'Jeton de service invalide.',
                'data' => null,
            ], 401);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response;
    }
}
