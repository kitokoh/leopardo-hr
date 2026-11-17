<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Core\Tenant\Domain\Models\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * IAM-L6 (#9096) — la MFA est IMPOSÉE aux comptes de plateforme.
 *
 * ## Le trou
 *
 * `PlatformAuthController::attemptLogin` ne demandait un code 2FA que SI
 * `two_fa_secret` était déjà posé. Rien n'obligeait à le poser : un compte
 * `super_admin` sans second facteur ouvrait une session omnisciente avec un
 * seul mot de passe.
 *
 * ## Ce que ce middleware ajoute
 *
 * Une fois authentifié sur `super_admin_api`, si
 * `config('platform.mfa_required')` est vrai et que le compte n'a PAS de
 * `two_fa_secret`, le jeton doit porter l'ability `platform:mfa-enrollment`
 * et la route doit être une route d'ENRÔLEMENT (liste blanche explicite).
 * Toute autre route répond `403 PLATFORM_MFA_REQUIRED` avec un
 * `enrollment_required: true` que le client lit pour router vers l'écran
 * d'enrôlement.
 *
 * ## Ce qu'il ne fait pas
 *
 * Il ne touche ni au login, ni au guard, ni aux rôles : un compte AVEC un
 * second facteur (donc déjà vérifié au login) n'est jamais concerné. Il ne
 * s'applique qu'aux routes `platform/*` / `admin/*` du guard plateforme.
 */
class EnsurePlatformMfaEnrolled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('platform.mfa_required', true)) {
            return $next($request);
        }

        $superAdmin = $request->user('super_admin_api');

        if (! $superAdmin instanceof SuperAdmin) {
            return $next($request);
        }

        // Compte déjà pourvu d'un second facteur : le login a vérifié le code.
        if (is_string($superAdmin->two_fa_secret) && $superAdmin->two_fa_secret !== '') {
            return $next($request);
        }

        // Sans second facteur : seule une route d'ENRÔLEMENT est ouverte, et
        // seulement au jeton d'enrôlement restreint émis par le login.
        $path = ltrim($request->path(), '/');
        $allowed = (array) config('platform.mfa_enrollment_paths', []);

        if (in_array($path, $allowed, true) && $this->tokenIsEnrollmentOnly($request)) {
            return $next($request);
        }

        return response()->json([
            'error' => 'PLATFORM_MFA_REQUIRED',
            'message' => 'PLATFORM_MFA_REQUIRED',
            'localized_message' => __('errors.PLATFORM_MFA_REQUIRED'),
            'enrollment_required' => true,
        ], 403);
    }

    /**
     * Le jeton courant est-il le jeton d'enrôlement (et rien d'autre) ?
     *
     * Un jeton plein (« * ») sur un compte sans second facteur ne doit PAS
     * pouvoir atteindre les données : seul `platform:mfa-enrollment` ouvre les
     * routes d'enrôlement. Fail-closed : pas de jeton → refus.
     */
    private function tokenIsEnrollmentOnly(Request $request): bool
    {
        $token = $request->user('super_admin_api')?->currentAccessToken();

        if ($token === null) {
            return false;
        }

        /** @var list<string> $abilities */
        $abilities = (array) $token->abilities;

        return in_array('platform:mfa-enrollment', $abilities, true);
    }
}
