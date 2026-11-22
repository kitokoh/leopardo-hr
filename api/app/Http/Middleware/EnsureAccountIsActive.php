<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Auth\Infrastructure\Services\AccountStatusGuard;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Issue #9809 — statut du compte sur les guards à SESSION (pas à jeton).
 *
 * Le hook Sanctum (`Sanctum::authenticateAccessTokensUsing`, voir
 * `AppServiceProvider::boot`) ne s'applique qu'aux guards `sanctum` : il ne
 * voit jamais un guard `session`. Deux groupes de routes sont donc hors de sa
 * portée :
 *
 *  - `auth:user_api` — le compte public, authentifié par COOKIE de session
 *    (login classique, OTP téléphone) ;
 *  - `auth:travel_customer` — le client grand public de la marketplace travel.
 *
 * Ce middleware comble le trou : il lit l'utilisateur déjà résolu par `auth`,
 * interroge le MÊME service de décision (`AccountStatusGuard`) et répond
 * `401` avec un code stable quand le compte n'est plus actif.
 *
 * ## Pourquoi un 401 et pas un 403
 *
 * Ce n'est pas un refus de permission (403 = « je te connais, tu n'as pas le
 * droit ») mais une identité qui n'est plus recevable (401 = « je ne te
 * reconnais plus comme une session valide »). Le client doit router vers
 * l'écran de connexion, ce que `401` provoque dans tous nos fronts ; un `403`
 * les laisse sur un écran qui semble cassé.
 */
final class EnsureAccountIsActive
{
    public function __construct(
        private readonly AccountStatusGuard $guard,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Model) {
            return $next($request);
        }

        if ($this->guard->accountIsActive($user)) {
            return $next($request);
        }

        $status = (string) ($user->getAttribute('status') ?? 'inactive');
        $code = $status === 'archived' ? 'ACCOUNT_ARCHIVED' : 'ACCOUNT_SUSPENDED';

        return response()->json([
            'error' => $code,
            'message' => $code,
            'localized_message' => __('errors.'.$code),
        ], 401);
    }
}
