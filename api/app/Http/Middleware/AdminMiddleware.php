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

        // Vérifier si l'utilisateur a les droits admin
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
        // Adapter selon votre système de rôles
        $role = $user->role ?? '';

        return $role === 'manager' || in_array($role, ['admin', 'super_admin']);
    }
}
