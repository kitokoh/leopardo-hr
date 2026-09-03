<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour vérifier les permissions administrateur
 */
class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        if (! $this->userIsAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Admin access required',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Vérifie si l'utilisateur est administrateur
     *
     * @param  mixed  $user
     */
    private function userIsAdmin($user): bool
    {
        // #6563 (audit auth) — les rôles littéraux `admin`/`super_admin`
        // n'étaient JAMAIS assignés sur les modèles protégés ici (Employee :
        // role ∈ employee|manager ; le super-admin vit dans sa propre table
        // avec son propre guard). Accepter ces valeurs était du code mort qui
        // élargissait la surface d'autorisation. Seul le rôle manager
        // `principal` administre cette surface.
        return is_object($user) && method_exists($user, 'hasManagerRole') && $user->hasManagerRole('principal');
    }
}
